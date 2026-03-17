<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\TaskRecipient;
use App\Models\Student;
use App\Models\InternshipAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TaskController extends Controller
{
    /**
     * Get all tasks created by teacher
     */
    public function index(Request $request)
    {
        $teacher = $request->user()->teacher;

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher profile not found'
            ], 404);
        }

        $tasks = Task::where('created_by', $teacher->id)
            ->withCount([
                'recipients',
                'submissions',
                'submissions as submitted_count' => fn($q) =>
                    $q->where('status', 'submitted'),
                'submissions as approved_count' => fn($q) =>
                    $q->where('status', 'approved'),
            ])
            ->when($request->status, fn($q, $status) =>
                $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $tasks
        ]);
    }

    /**
     * Create new task
     */
    public function store(Request $request)
    {
        $teacher = $request->user()->teacher;

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'expected_output' => 'nullable|string',
            'deadline' => 'required|date|after:today',
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id'
        ]);

        DB::beginTransaction();
        try {
            // Create task
            $task = Task::create([
                'created_by' => $teacher->id,
                'school_id' => $teacher->school_id,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'expected_output' => $validated['expected_output'] ?? null,
                'deadline' => $validated['deadline'],
                'status' => 'active'
            ]);

            // Add recipients
            foreach ($validated['student_ids'] as $studentId) {
                // Verify student is supervised by this teacher
                $student = Student::find($studentId);
                $assignment = InternshipAssignment::where('student_id', $studentId)
                    ->where('supervisor_teacher_id', $teacher->id)
                    ->where('status', 'active')
                    ->first();

                if (!$assignment) {
                    continue; // Skip if not supervised
                }

                // Create recipient
                TaskRecipient::create([
                    'task_id' => $task->id,
                    'student_id' => $studentId
                ]);

                // Create submission record
                TaskSubmission::create([
                    'task_id' => $task->id,
                    'student_id' => $studentId,
                    'assignment_id' => $assignment->id,
                    'status' => 'new'
                ]);
            }

            DB::commit();

            $task->load(['recipients', 'submissions']);

            return response()->json([
                'success' => true,
                'message' => 'Task created successfully',
                'data' => $task
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get task detail with submissions
     */
    public function show($id)
    {
        $teacher = request()->user()->teacher;

        $task = Task::where('id', $id)
            ->where('created_by', $teacher->id)
            ->with([
                'recipients.student.user',
                'submissions.student.user'
            ])
            ->withCount('submissions')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $task
        ]);
    }

    /**
     * Update task
     */
    public function update(Request $request, $id)
    {
        $teacher = $request->user()->teacher;

        $task = Task::where('id', $id)
            ->where('created_by', $teacher->id)
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'expected_output' => 'nullable|string',
            'deadline' => 'sometimes|date|after:today',
            'status' => 'sometimes|in:active,archived'
        ]);

        $task->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully',
            'data' => $task
        ]);
    }

    /**
     * Delete task
     */
    public function destroy($id)
    {
        $teacher = request()->user()->teacher;

        $task = Task::where('id', $id)
            ->where('created_by', $teacher->id)
            ->firstOrFail();

        // Check if any submissions approved
        $hasApprovedSubmissions = $task->submissions()
            ->where('status', 'approved')
            ->exists();

        if ($hasApprovedSubmissions) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete task with approved submissions. Archive instead.'
            ], 422);
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully'
        ]);
    }

    /**
     * Get submissions for a task
     */
    public function submissions($taskId)
    {
        $teacher = request()->user()->teacher;

        $task = Task::where('id', $taskId)
            ->where('created_by', $teacher->id)
            ->firstOrFail();

        $submissions = TaskSubmission::where('task_id', $taskId)
            ->with(['student.user', 'assignment.company'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $submissions
        ]);
    }

    /**
     * Approve submission
     */
    public function approveSubmission(Request $request, $submissionId)
    {
        $teacher = $request->user()->teacher;

        $submission = TaskSubmission::with('task')->findOrFail($submissionId);

        // Verify teacher created the task
        if ($submission->task->created_by !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to approve this submission'
            ], 403);
        }

        if ($submission->status !== 'submitted' && $submission->status !== 'late') {
            return response()->json([
                'success' => false,
                'message' => 'Can only approve submitted tasks'
            ], 422);
        }

        $submission->update([
            'status' => 'approved',
            'approved_at' => now(),
            'teacher_feedback' => $request->feedback ?? null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Submission approved',
            'data' => $submission
        ]);
    }

    /**
     * Request revision
     */
    public function requestRevision(Request $request, $submissionId)
    {
        $teacher = $request->user()->teacher;

        $validated = $request->validate([
            'feedback' => 'required|string',
            'revision_deadline' => 'required|date|after:today'
        ]);

        $submission = TaskSubmission::with('task')->findOrFail($submissionId);

        // Verify teacher created the task
        if ($submission->task->created_by !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to request revision'
            ], 403);
        }

        $submission->update([
            'status' => 'revision',
            'teacher_feedback' => $validated['feedback'],
            'revision_deadline' => $validated['revision_deadline']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Revision requested',
            'data' => $submission
        ]);
    }

    /**
     * Add recipients to existing task
     */
    public function addRecipients(Request $request, $taskId)
    {
        $teacher = $request->user()->teacher;

        $task = Task::where('id', $taskId)
            ->where('created_by', $teacher->id)
            ->firstOrFail();

        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id'
        ]);

        $addedCount = 0;

        foreach ($validated['student_ids'] as $studentId) {
            // Check if already recipient
            $exists = TaskRecipient::where('task_id', $taskId)
                ->where('student_id', $studentId)
                ->exists();

            if ($exists) {
                continue;
            }

            // Verify supervised by teacher
            $assignment = InternshipAssignment::where('student_id', $studentId)
                ->where('supervisor_teacher_id', $teacher->id)
                ->where('status', 'active')
                ->first();

            if (!$assignment) {
                continue;
            }

            // Add recipient
            TaskRecipient::create([
                'task_id' => $taskId,
                'student_id' => $studentId
            ]);

            // Create submission
            TaskSubmission::create([
                'task_id' => $taskId,
                'student_id' => $studentId,
                'assignment_id' => $assignment->id,
                'status' => 'new'
            ]);

            $addedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$addedCount} students added to task",
            'added_count' => $addedCount
        ]);
    }
}
