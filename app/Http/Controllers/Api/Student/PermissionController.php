<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Attendance;
use App\Models\InternshipAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PermissionController extends Controller
{
    /**
     * Get all permissions for student
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

        $permissions = Permission::where('student_id', $student->id)
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->month, fn($q, $month) => $q->whereMonth('date', $month))
            ->with(['reviewedBy'])
            ->orderBy('date', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    /**
     * Submit a new permission request
     */
    public function store(Request $request)
    {
        $student = $request->user()->student;

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student profile not found'
            ], 404);
        }

        // Get active assignment
        $assignment = InternshipAssignment::where('student_id', $student->id)
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'You must have an active internship to request permission'
            ], 422);
        }

        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'type' => 'required|in:sick,permission,other', // Kategori izin
            'reason' => 'required|string|max:500',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048', // Surat keterangan
        ]);

        // Check if already have permission for this date
        $existing = Permission::where('student_id', $student->id)
            ->where('date', $validated['date'])
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a permission request for this date'
            ], 422);
        }

        // Check if already have attendance for this date
        $hasAttendance = Attendance::where('assignment_id', $assignment->id)
            ->where('date', $validated['date'])
            ->whereNotNull('clock_in_time')
            ->exists();

        if ($hasAttendance) {
            return response()->json([
                'success' => false,
                'message' => 'You have already clocked in on this date. Cannot request permission.'
            ], 422);
        }

        // Upload attachment if exists
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')
                ->store('permission-attachments', 'public');
        }

        $permission = Permission::create([
            'student_id' => $student->id,
            'assignment_id' => $assignment->id,
            'date' => $validated['date'],
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'attachment' => $attachmentPath,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission request submitted successfully',
            'data' => $permission
        ], 201);
    }

    /**
     * Get permission detail
     */
    public function show($id)
    {
        $student = request()->user()->student;

        $permission = Permission::where('student_id', $student->id)
            ->where('id', $id)
            ->with(['reviewedBy', 'assignment.company'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $permission
        ]);
    }

    /**
     * Cancel permission (only if pending)
     */
    public function destroy($id)
    {
        $student = request()->user()->student;

        $permission = Permission::where('student_id', $student->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($permission->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel permission that has been reviewed'
            ], 422);
        }

        // Delete attachment if exists
        if ($permission->attachment) {
            Storage::disk('public')->delete($permission->attachment);
        }

        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission request cancelled'
        ]);
    }

    /**
     * Get pending permissions (for teacher/admin to review)
     * This should be in TeacherPermissionController, but included here for reference
     */
    public function pending(Request $request)
    {
        // This method will be in Teacher/PermissionController
        // Included here for completeness

        $teacher = $request->user()->teacher;

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher profile not found'
            ], 404);
        }

        // Get assignments supervised by this teacher
        $assignmentIds = InternshipAssignment::where('supervisor_teacher_id', $teacher->id)
            ->pluck('id');

        $permissions = Permission::whereIn('assignment_id', $assignmentIds)
            ->where('status', 'pending')
            ->with(['student.user', 'assignment.company'])
            ->orderBy('date', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    /**
     * Approve permission (for teacher/admin)
     * This should be in TeacherPermissionController
     */
    public function approve(Request $request, $id)
    {
        $teacher = $request->user()->teacher;

        $permission = Permission::with('assignment')->findOrFail($id);

        // Verify teacher is supervisor
        if ($permission->assignment->supervisor_teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not the supervisor for this student'
            ], 403);
        }

        if ($permission->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Permission already reviewed'
            ], 422);
        }

        $permission->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $request->review_notes,
        ]);

        // AUTO UPDATE ATTENDANCE: Set as permission
        $attendance = Attendance::firstOrCreate(
            [
                'assignment_id' => $permission->assignment_id,
                'date' => $permission->date,
            ],
            [
                'status' => 'permission',
                'verification_status' => 'approved',
            ]
        );

        // If attendance already exists, update it
        if (!$attendance->wasRecentlyCreated) {
            $attendance->update([
                'status' => 'permission',
                'verification_status' => 'approved',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permission approved',
            'data' => $permission
        ]);
    }

    /**
     * Reject permission (for teacher/admin)
     * This should be in TeacherPermissionController
     */
    public function reject(Request $request, $id)
    {
        $teacher = $request->user()->teacher;

        $validated = $request->validate([
            'review_notes' => 'required|string|max:500'
        ]);

        $permission = Permission::with('assignment')->findOrFail($id);

        // Verify teacher is supervisor
        if ($permission->assignment->supervisor_teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not the supervisor for this student'
            ], 403);
        }

        $permission->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission rejected',
            'data' => $permission
        ]);
    }

    /**
     * Get permission statistics for student
     */
    public function statistics(Request $request)
    {
        $student = $request->user()->student;

        $stats = [
            'total' => Permission::where('student_id', $student->id)->count(),
            'pending' => Permission::where('student_id', $student->id)
                ->where('status', 'pending')->count(),
            'approved' => Permission::where('student_id', $student->id)
                ->where('status', 'approved')->count(),
            'rejected' => Permission::where('student_id', $student->id)
                ->where('status', 'rejected')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
