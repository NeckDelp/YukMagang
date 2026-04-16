<?php

namespace App\Http\Controllers\Api\Mentor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\InternshipAssignment;
use App\Models\CompanySupervisor;

class MentorAttendanceController extends Controller
{
    private function getSupervisor($user)
    {
        return CompanySupervisor::where('user_id', $user->id)->first();
    }

    public function index(Request $request)
    {
        $supervisor = $this->getSupervisor($request->user());
        if (!$supervisor) return response()->json(['success' => false, 'message' => 'Not a valid mentor'], 403);

        $attendances = Attendance::whereHas('assignment', function ($q) use ($supervisor) {
            $q->where('company_supervisor_id', $supervisor->id);
        })
        ->with(['assignment.student.user'])
        ->when($request->status, fn($q, $status) => $q->where('status', $status))
        ->when($request->search, function($q, $search) {
            $q->whereHas('assignment.student.user', function($q2) use ($search) {
                $q2->where('name', 'like', "%{$search}%");
            })->orWhereHas('assignment.student', function($q2) use ($search) {
                $q2->where('nis', 'like', "%{$search}%");
            });
        })
        ->orderBy('date', 'desc')
        ->paginate($request->per_page ?? 15);

        return response()->json(['success' => true, 'data' => $attendances]);
    }

    public function statistics(Request $request)
    {
        $supervisor = $this->getSupervisor($request->user());
        if (!$supervisor) return response()->json(['success' => false], 403);

        $baseQuery = Attendance::whereHas('assignment', function ($q) use ($supervisor) {
            $q->where('company_supervisor_id', $supervisor->id);
        });

        $stats = [
            'present' => (clone $baseQuery)->where('status', 'present')->count(),
            'permission' => (clone $baseQuery)->where('status', 'permission')->count(),
            'late' => (clone $baseQuery)->where('status', 'late')->count(),
            'absent' => (clone $baseQuery)->where('status', 'absent')->count(),
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }

    public function byAssignment(Request $request, $assignmentId)
    {
        $supervisor = $this->getSupervisor($request->user());
        if (!$supervisor) return response()->json(['success' => false, 'message' => 'Not a valid mentor'], 403);

        $assignment = InternshipAssignment::where('id', $assignmentId)
            ->where('company_supervisor_id', $supervisor->id)
            ->with(['student.user'])
            ->firstOrFail();

        $attendances = Attendance::where('assignment_id', $assignmentId)
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'assignment' => [
                    'id' => $assignment->id,
                    'start_date' => $assignment->start_date,
                    'end_date' => $assignment->end_date,
                    'student_name' => $assignment->student->user->name ?? '',
                ],
                'attendances' => $attendances
            ]
        ]);
    }
}
