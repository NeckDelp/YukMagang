<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\InternshipAssignment;
use Illuminate\Http\Request;

class AttendanceVerificationController extends Controller
{
    /**
     * Get pending attendances for verification
     */
    public function pending(Request $request)
    {
        $teacher = $request->user()->teacher;

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher profile not found'
            ], 404);
        }

        // Get all assignments supervised by this teacher
        $assignmentIds = InternshipAssignment::where('supervisor_teacher_id', $teacher->id)
            ->pluck('id');

        $attendances = Attendance::whereIn('assignment_id', $assignmentIds)
            ->where('verification_status', 'pending')
            ->with(['assignment.student.user', 'assignment.company'])
            ->when($request->date_from, fn($q, $date) =>
                $q->whereDate('date', '>=', $date))
            ->when($request->date_to, fn($q, $date) =>
                $q->whereDate('date', '<=', $date))
            ->when($request->status, fn($q, $status) =>
                $q->where('status', $status))
            ->orderBy('date', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $attendances
        ]);
    }

    /**
     * Approve attendance
     */
    public function approve(Request $request, $id)
    {
        $teacher = $request->user()->teacher;

        $attendance = Attendance::with('assignment')->findOrFail($id);

        // Verify teacher is supervisor
        if ($attendance->assignment->supervisor_teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not the supervisor for this student'
            ], 403);
        }

        // Already verified
        if ($attendance->verification_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Attendance already verified'
            ], 422);
        }

        $attendance->update([
            'verification_status' => 'approved',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance approved',
            'data' => $attendance
        ]);
    }

    /**
     * Reject attendance
     */
    public function reject(Request $request, $id)
    {
        $teacher = $request->user()->teacher;

        $validated = $request->validate([
            'notes' => 'required|string|max:500'
        ]);

        $attendance = Attendance::with('assignment')->findOrFail($id);

        // Verify teacher is supervisor
        if ($attendance->assignment->supervisor_teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not the supervisor for this student'
            ], 403);
        }

        $attendance->update([
            'verification_status' => 'rejected',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'notes' => $validated['notes']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance rejected',
            'data' => $attendance
        ]);
    }

    /**
     * Get verification statistics
     */
    public function statistics(Request $request)
    {
        $teacher = $request->user()->teacher;

        $assignmentIds = InternshipAssignment::where('supervisor_teacher_id', $teacher->id)
            ->pluck('id');

        $stats = [
            'pending' => Attendance::whereIn('assignment_id', $assignmentIds)
                ->where('verification_status', 'pending')->count(),
            'approved' => Attendance::whereIn('assignment_id', $assignmentIds)
                ->where('verification_status', 'approved')->count(),
            'rejected' => Attendance::whereIn('assignment_id', $assignmentIds)
                ->where('verification_status', 'rejected')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Bulk approve attendances
     */
    public function bulkApprove(Request $request)
    {
        $teacher = $request->user()->teacher;

        $validated = $request->validate([
            'attendance_ids' => 'required|array|min:1',
            'attendance_ids.*' => 'exists:attendances,id'
        ]);

        // Get attendances and verify supervisor
        $attendances = Attendance::with('assignment')
            ->whereIn('id', $validated['attendance_ids'])
            ->get();

        $approvedCount = 0;
        foreach ($attendances as $attendance) {
            if ($attendance->assignment->supervisor_teacher_id === $teacher->id
                && $attendance->verification_status === 'pending') {

                $attendance->update([
                    'verification_status' => 'approved',
                    'verified_by' => $request->user()->id,
                    'verified_at' => now(),
                ]);
                $approvedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$approvedCount} attendances approved",
            'approved_count' => $approvedCount
        ]);
    }
}
