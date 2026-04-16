<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\InternshipAssignment;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StudentAttendanceController extends Controller
{
    private function getStudentAssignment(Request $request)
    {
        $student = $request->user()->student;

        if (!$student) {
            abort(403, 'Student profile not found');
        }

        $assignment = InternshipAssignment::where('student_id', $student->id)
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            abort(403, 'No active internship assignment found');
        }

        return $assignment;
    }

    public function today(Request $request)
    {
        $assignment = $this->getStudentAssignment($request);
        $today = Carbon::today()->format('Y-m-d');

        $attendance = Attendance::where('assignment_id', $assignment->id)
            ->whereDate('date', $today)
            ->first();

        // Check if student has permission today
        $permission = \App\Models\Permission::where('assignment_id', $assignment->id)
            ->whereDate('permission_date', $today)
            ->where('status', 'approved')
            ->first();

        $todayData = [
            'is_working_day' => Carbon::today()->isWeekday(), // assuming M-F
            'has_permission' => $permission ? true : false,
            'attendance' => $attendance,
            'can_clock_in' => !$attendance || !$attendance->clock_in_time,
            'can_clock_out' => $attendance && $attendance->clock_in_time && !$attendance->clock_out_time,
            'company_work_hours' => [
                'start' => '08:00',
                'end' => '17:00'
            ],
            'current_time' => Carbon::now()->format('H:i:s')
        ];

        return response()->json([
            'success' => true,
            'data' => $todayData
        ]);
    }

    public function index(Request $request)
    {
        $assignment = $this->getStudentAssignment($request);

        $attendances = Attendance::where('assignment_id', $assignment->id)
            ->when($request->month, function($q, $month) {
                $q->whereMonth('date', $month);
            })
            ->orderBy('date', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $attendances
        ]);
    }

    public function clockIn(Request $request)
    {
        $assignment = $this->getStudentAssignment($request);
        $today = Carbon::today()->format('Y-m-d');
        $now = Carbon::now()->format('H:i:s');

        $existing = Attendance::where('assignment_id', $assignment->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing && $existing->clock_in_time) {
            return response()->json([
                'success' => false,
                'message' => 'Already clocked in today'
            ], 422);
        }

        // Determine if late (e.g. after 08:00)
        // Hardcoded threshold for simplicity, could be from position's working hours
        $status = Carbon::now()->format('H:i') > '08:00' ? 'late' : 'present';

        if ($existing) {
            $existing->update([
                'clock_in_time' => $now,
                'clock_in_ip' => $request->ip(),
                'status' => $existing->status === 'permit' ? 'permit' : $status
            ]);
            $attendance = $existing;
        } else {
            $attendance = Attendance::create([
                'assignment_id' => $assignment->id,
                'date' => $today,
                'clock_in_time' => $now,
                'clock_in_ip' => $request->ip(),
                'status' => $status,
                'verification_status' => 'pending'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Clocked in successfully',
            'data' => $attendance
        ]);
    }

    public function clockOut(Request $request)
    {
        $assignment = $this->getStudentAssignment($request);
        $today = Carbon::today()->format('Y-m-d');
        $now = Carbon::now()->format('H:i:s');

        $attendance = Attendance::where('assignment_id', $assignment->id)
            ->whereDate('date', $today)
            ->first();

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'You have not clocked in today'
            ], 422);
        }

        if ($attendance->clock_out_time) {
            return response()->json([
                'success' => false,
                'message' => 'Already clocked out today'
            ], 422);
        }

        $attendance->update([
            'clock_out_time' => $now,
            'clock_out_ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Clocked out successfully',
            'data' => $attendance
        ]);
    }
}
