<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\TaskRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class TaskSubmissionController extends Controller
{
    /**
     * Get all tasks for student
     */
    public function index(Request $request)
    {
        $student = $request->user()->student;

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student profile not found'
            ], 404);
        }

        // Get tasks where student is recipient
        $submissions = TaskSubmission::where('student_id', $student->id)
            ->with(['task', 'task.createdBy.user'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $submissions
        ]);
    }

    /**
     * Get task detail & submission
     */
    public function show(Request $request, $taskId)
    {
        $student = $request->user()->student;

        $task = Task::with('createdBy.user')->findOrFail($taskId);

        // Check if student is recipient
        $isRecipient = TaskRecipient::where('task_id', $taskId)
            ->where('student_id', $student->id)
            ->exists();

        if (!$isRecipient) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this task'
            ], 403);
        }

        // Get or create submission
        $submission = TaskSubmission::firstOrCreate(
            [
                'task_id' => $taskId,
                'student_id' => $student->id,
            ],
            [
                'assignment_id' => $student->internshipAssignments()
                    ->where('status', 'active')
                    ->first()->id ?? null,
                'status' => Carbon::now()->greaterThan($task->deadline) ? 'late' : 'new'
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'task' => $task,
                'submission' => $submission
            ]
        ]);
    }

    /**
     * Submit task (upload file)
     */
    public function submit(Request $request, $taskId)
    {
        $student = $request->user()->student;

        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240', // 10MB
            'notes' => 'nullable|string|max:1000'
        ]);

        $task = Task::findOrFail($taskId);

        // Check if student is recipient
        $isRecipient = TaskRecipient::where('task_id', $taskId)
            ->where('student_id', $student->id)
            ->exists();

        if (!$isRecipient) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this task'
            ], 403);
        }

        $submission = TaskSubmission::where('task_id', $taskId)
            ->where('student_id', $student->id)
            ->first();

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }

        // Check if already approved
        if ($submission->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Task already approved, cannot resubmit'
            ], 422);
        }

        // Delete old file if exists
        if ($submission->file_path) {
            Storage::disk('public')->delete($submission->file_path);
        }

        // Upload new file
        $filePath = $request->file('file')->store('task-submissions', 'public');

        // Determine status
        $now = Carbon::now();
        $deadline = Carbon::parse($task->deadline);

        // If there's revision deadline, use that; otherwise use original deadline
        $effectiveDeadline = $submission->revision_deadline
            ? Carbon::parse($submission->revision_deadline)
            : $deadline;

        $status = $now->greaterThan($effectiveDeadline) ? 'late' : 'submitted';

        $submission->update([
            'file_path' => $filePath,
            'student_notes' => $validated['notes'] ?? null,
            'status' => $status,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task submitted successfully',
            'data' => $submission
        ]);
    }

    /**
     * Get task statistics
     */
    public function statistics(Request $request)
    {
        $student = $request->user()->student;

        $stats = [
            'total' => TaskSubmission::where('student_id', $student->id)->count(),
            'new' => TaskSubmission::where('student_id', $student->id)
                ->where('status', 'new')->count(),
            'in_progress' => TaskSubmission::where('student_id', $student->id)
                ->where('status', 'in_progress')->count(),
            'submitted' => TaskSubmission::where('student_id', $student->id)
                ->where('status', 'submitted')->count(),
            'revision' => TaskSubmission::where('student_id', $student->id)
                ->where('status', 'revision')->count(),
            'approved' => TaskSubmission::where('student_id', $student->id)
                ->where('status', 'approved')->count(),
            'late' => TaskSubmission::where('student_id', $student->id)
                ->where('status', 'late')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Mark task as in progress (optional)
     */
    public function markInProgress(Request $request, $taskId)
    {
        $student = $request->user()->student;

        $submission = TaskSubmission::where('task_id', $taskId)
            ->where('student_id', $student->id)
            ->first();

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found'
            ], 404);
        }

        if ($submission->status === 'new') {
            $submission->update(['status' => 'in_progress']);
        }

        return response()->json([
            'success' => true,
            'data' => $submission
        ]);
    }
}
