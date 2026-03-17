<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Attendance;
use App\Models\InternshipAssignment;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * Get pending permissions
     */
    public function pending(Request $request)
    {
        $teacher = $request->user()->teacher;

        $assignmentIds = InternshipAssignment::where('supervisor_teacher_id', $teacher->id)
            ->pluck('id');

        $permissions = Permission::whereIn('assignment_id', $assignmentIds)
            ->where('status', 'pending')
            ->with(['assignment.student.user', 'assignment.company'])
            ->orderBy('permission_date', 'asc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    /**
     * Approve permission
     */
    public function approve(Request $request, $id)
    {
        $teacher = $request->user()->teacher;

        $permission = Permission::with('assignment')->findOrFail($id);

        // Verify supervisor
        if ($permission->assignment->supervisor_teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Not authorized'
            ], 403);
        }

        if ($permission->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Permission already processed'
            ], 422);
        }

        // Approve permission
        $permission->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $request->notes ?? null
        ]);

        // Update attendance to approved permission
        $attendance = Attendance::where('assignment_id', $permission->assignment_id)
            ->where('date', $permission->permission_date)
            ->first();

        if ($attendance) {
            $attendance->update([
                'status' => 'permission',
                'verification_status' => 'approved',
                'verified_by' => $request->user()->id,
                'verified_at' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permission approved',
            'data' => $permission
        ]);
    }

    /**
     * Reject permission
     */
    public function reject(Request $request, $id)
    {
        $teacher = $request->user()->teacher;

        $validated = $request->validate([
            'notes' => 'required|string|max:500'
        ]);

        $permission = Permission::with('assignment')->findOrFail($id);

        // Verify supervisor
        if ($permission->assignment->supervisor_teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Not authorized'
            ], 403);
        }

        // Reject permission
        $permission->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['notes']
        ]);

        // Revert attendance status
        $attendance = Attendance::where('assignment_id', $permission->assignment_id)
            ->where('date', $permission->permission_date)
            ->first();

        if ($attendance) {
            $notes = json_decode($attendance->notes, true);
            $originalStatus = $notes['original_status'] ?? 'absent';

            $attendance->update([
                'status' => $originalStatus,
                'verification_status' => 'pending',
                'notes' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permission rejected',
            'data' => $permission
        ]);
    }
}
