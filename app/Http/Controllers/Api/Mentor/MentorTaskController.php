<?php

namespace App\Http\Controllers\Api\Mentor;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\TaskRecipient;
use App\Models\Student;
use App\Models\InternshipAssignment;
use App\Models\CompanySupervisor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MentorTaskController extends Controller
{
    private function getSupervisor($user)
    {
        return CompanySupervisor::where('user_id', $user->id)->first();
    }

    public function index(Request $request)
    {
        $tasks = Task::where('created_by', $request->user()->id)
            ->withCount([
                'recipients',
                'submissions',
                'submissions as submitted_count' => fn($q) => $q->where('status', 'submitted'),
                'submissions as approved_count' => fn($q) => $q->where('status', 'approved'),
            ])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json(['success' => true, 'data' => $tasks]);
    }

    public function store(Request $request)
    {
        $supervisor = $this->getSupervisor($request->user());
        if (!$supervisor) return response()->json(['success' => false, 'message' => 'Not a valid mentor'], 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'expected_output' => 'nullable|string',
            'deadline' => 'required|date|after:today',
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'attachment_file' => 'nullable|file|mimes:pdf,doc,docx,zip,rar|max:10240'
        ]);

        DB::beginTransaction();
        try {
            // Find school_id from the first student's assignment
            $firstAssignment = InternshipAssignment::where('student_id', $validated['student_ids'][0])
                ->where('company_supervisor_id', $supervisor->id)
                ->where('status', 'active')->first();
            
            if (!$firstAssignment) {
                throw new \Exception("The first student chosen is not assigned to you.");
            }

            $attachmentPath = null;
            if ($request->hasFile('attachment_file')) {
                $attachmentPath = $request->file('attachment_file')->store('tasks_attachments', 'public');
            }

            $task = Task::create([
                'created_by' => $request->user()->id,
                'school_id' => $firstAssignment->school_id,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'expected_output' => $validated['expected_output'] ?? null,
                'deadline' => $validated['deadline'],
                'attachment_file' => $attachmentPath,
                'status' => 'active'
            ]);

            foreach ($validated['student_ids'] as $studentId) {
                $assignment = InternshipAssignment::where('student_id', $studentId)
                    ->where('company_supervisor_id', $supervisor->id)
                    ->where('status', 'active')
                    ->first();

                if (!$assignment) continue;

                TaskRecipient::create(['task_id' => $task->id, 'student_id' => $studentId]);
                TaskSubmission::create([
                    'task_id' => $task->id,
                    'student_id' => $studentId,
                    'assignment_id' => $assignment->id,
                    'status' => 'new'
                ]);
            }

            DB::commit();
            $task->load(['recipients', 'submissions']);
            return response()->json(['success' => true, 'message' => 'Task created', 'data' => $task], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        // Find the task - also check by supervisor to ensure ownership
        $task = Task::where('id', $id)
            ->where('created_by', request()->user()->id)
            ->with(['recipients.student.user', 'submissions.student.user'])
            ->withCount('submissions')
            ->first();

        if (!$task) {
            // Also try to find it if mentor created it
            $task = Task::where('id', $id)
                ->with(['recipients.student.user', 'submissions.student.user'])
                ->withCount('submissions')
                ->first();

            if (!$task) {
                return response()->json(['success' => false, 'message' => 'Task not found'], 404);
            }
        }

        $submissions = $task->submissions;

        return response()->json([
            'success' => true,
            'data' => [
                'task' => $task,
                'submissions' => $submissions
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $task = Task::where('id', $id)->where('created_by', $request->user()->id)->firstOrFail();
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'expected_output' => 'nullable|string',
            'deadline' => 'sometimes|date|after:today',
            'status' => 'sometimes|in:active,archived'
        ]);
        $task->update($validated);
        return response()->json(['success' => true, 'message' => 'Task updated', 'data' => $task]);
    }

    public function destroy($id)
    {
        $task = Task::where('id', $id)->where('created_by', request()->user()->id)->firstOrFail();
        if ($task->submissions()->where('status', 'approved')->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete task with approved submissions.'], 422);
        }
        $task->delete();
        return response()->json(['success' => true, 'message' => 'Task deleted']);
    }

    public function approveSubmission(Request $request, $submissionId)
    {
        $submission = TaskSubmission::with('task')->findOrFail($submissionId);
        if ($submission->task->created_by !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        if (!in_array($submission->status, ['submitted', 'late'])) {
            return response()->json(['success' => false, 'message' => 'Invalid status'], 422);
        }
        $submission->update([
            'status' => 'approved',
            'approved_at' => now(),
            'teacher_feedback' => $request->feedback ?? null
        ]);
        return response()->json(['success' => true, 'message' => 'Submission approved', 'data' => $submission]);
    }

    public function requestRevision(Request $request, $submissionId)
    {
        $validated = $request->validate([
            'feedback' => 'required|string',
            'revision_deadline' => 'required|date|after:today'
        ]);
        $submission = TaskSubmission::with('task')->findOrFail($submissionId);
        if ($submission->task->created_by !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        $submission->update([
            'status' => 'revision',
            'teacher_feedback' => $validated['feedback'],
            'revision_deadline' => $validated['revision_deadline']
        ]);
        return response()->json(['success' => true, 'message' => 'Revision requested', 'data' => $submission]);
    }
}
