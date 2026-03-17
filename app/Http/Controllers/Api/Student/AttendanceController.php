<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\InternshipAssignment;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Get today's attendance status
     */
    public function today(Request $request)
    {
        $student = $request->user()->student;
        $assignment = $this->getActiveAssignment($student);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'No active internship assignment'
            ], 404);
        }

        $today = Carbon::today();

        // Check if today is working day
        $isWorkingDay = $this->isWorkingDay($assignment->company, $today);

        if (!$isWorkingDay) {
            return response()->json([
                'success' => true,
                'data' => [
                    'is_working_day' => false,
                    'message' => 'Today is not a working day',
                    'attendance' => null,
                    'can_clock_in' => false,
                    'can_clock_out' => false,
                ]
            ]);
        }

        // **NEW: Check if has approved permission for today**
        $hasApprovedPermission = Permission::where('student_id', $student->id)
            ->where('date', $today)
            ->where('status', 'approved')
            ->exists();

        if ($hasApprovedPermission) {
            // Get or create attendance with permission status
            $attendance = Attendance::firstOrCreate(
                [
                    'assignment_id' => $assignment->id,
                    'date' => $today
                ],
                [
                    'status' => 'permission',
                    'verification_status' => 'approved'
                ]
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'is_working_day' => true,
                    'has_permission' => true,
                    'message' => 'You have approved permission for today',
                    'attendance' => $attendance,
                    'can_clock_in' => false,
                    'can_clock_out' => false,
                    'company_work_hours' => [
                        'start' => $assignment->company->work_start_time,
                        'end' => $assignment->company->work_end_time
                    ]
                ]
            ]);
        }

        // Normal attendance flow
        $attendance = Attendance::firstOrCreate(
            [
                'assignment_id' => $assignment->id,
                'date' => $today
            ],
            [
                'status' => 'absent',
                'verification_status' => 'pending'
            ]
        );

        $canClockIn = !$attendance->clock_in_time;
        $canClockOut = $attendance->clock_in_time && !$attendance->clock_out_time;

        return response()->json([
            'success' => true,
            'data' => [
                'is_working_day' => true,
                'has_permission' => false,
                'attendance' => $attendance,
                'can_clock_in' => $canClockIn,
                'can_clock_out' => $canClockOut,
                'company_work_hours' => [
                    'start' => $assignment->company->work_start_time,
                    'end' => $assignment->company->work_end_time
                ],
                'current_time' => Carbon::now()->format('H:i:s'),
            ]
        ]);
    }

    /**
     * Clock In
     */
    public function clockIn(Request $request)
    {
        $student = $request->user()->student;
        $assignment = $this->getActiveAssignment($student);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'No active internship assignment'
            ], 404);
        }

        $today = Carbon::today();
        $now = Carbon::now();

        // Check if working day
        if (!$this->isWorkingDay($assignment->company, $today)) {
            return response()->json([
                'success' => false,
                'message' => 'Today is not a working day'
            ], 422);
        }

        // **NEW: Check if has approved permission**
        $hasApprovedPermission = Permission::where('student_id', $student->id)
            ->where('date', $today)
            ->where('status', 'approved')
            ->exists();

        if ($hasApprovedPermission) {
            return response()->json([
                'success' => false,
                'message' => 'You have approved permission for today. Cannot clock in.'
            ], 422);
        }

        $attendance = Attendance::where('assignment_id', $assignment->id)
            ->where('date', $today)
            ->first();

        if (!$attendance) {
            $attendance = new Attendance([
                'assignment_id' => $assignment->id,
                'date' => $today
            ]);
        }

        // Check if already clocked in
        if ($attendance->clock_in_time) {
            return response()->json([
                'success' => false,
                'message' => 'You have already clocked in today'
            ], 422);
        }

        // Get IP address
        $ipAddress = $request->ip();

        // Calculate status (late or on time)
        $workStartTime = Carbon::parse($assignment->company->work_start_time);
        $currentTime = Carbon::parse($now->format('H:i:s'));

        // **CONFIGURABLE: Late tolerance**
        // You can add late_tolerance_minutes to companies table
        $lateTolerance = $assignment->company->late_tolerance_minutes ?? 0;
        $workStartWithTolerance = $workStartTime->copy()->addMinutes($lateTolerance);

        $status = $currentTime->greaterThan($workStartWithTolerance) ? 'late' : 'present';

        $attendance->clock_in_time = $now->format('H:i:s');
        $attendance->clock_in_ip = $ipAddress;
        $attendance->status = $status;
        $attendance->verification_status = 'pending';
        $attendance->save();

        $message = $status === 'late'
            ? 'Clocked in (Late)'
            : 'Clocked in successfully';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $attendance
        ]);
    }

    /**
     * Clock Out
     */
    public function clockOut(Request $request)
    {
        $student = $request->user()->student;
        $assignment = $this->getActiveAssignment($student);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'No active internship assignment'
            ], 404);
        }

        $today = Carbon::today();
        $now = Carbon::now();

        // **NEW: Check if has approved permission**
        $hasApprovedPermission = Permission::where('student_id', $student->id)
            ->where('date', $today)
            ->where('status', 'approved')
            ->exists();

        if ($hasApprovedPermission) {
            return response()->json([
                'success' => false,
                'message' => 'You have approved permission for today. Cannot clock out.'
            ], 422);
        }

        $attendance = Attendance::where('assignment_id', $assignment->id)
            ->where('date', $today)
            ->first();

        if (!$attendance || !$attendance->clock_in_time) {
            return response()->json([
                'success' => false,
                'message' => 'You must clock in first'
            ], 422);
        }

        if ($attendance->clock_out_time) {
            return response()->json([
                'success' => false,
                'message' => 'You have already clocked out today'
            ], 422);
        }

        $ipAddress = $request->ip();

        // Check early leave
        $workEndTime = Carbon::parse($assignment->company->work_end_time);
        $currentTime = Carbon::parse($now->format('H:i:s'));

        // **CONFIGURABLE: Early leave tolerance**
        $earlyLeaveTolerance = $assignment->company->early_leave_tolerance_minutes ?? 0;
        $workEndWithTolerance = $workEndTime->copy()->subMinutes($earlyLeaveTolerance);

        if ($currentTime->lessThan($workEndWithTolerance)) {
            $attendance->status = 'early_leave';
        }

        $attendance->clock_out_time = $now->format('H:i:s');
        $attendance->clock_out_ip = $ipAddress;
        $attendance->save();

        $message = $attendance->status === 'early_leave'
            ? 'Clocked out (Early Leave)'
            : 'Clocked out successfully';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $attendance
        ]);
    }

    /**
     * Get attendance history
     */
    public function history(Request $request)
    {
        $student = $request->user()->student;
        $assignment = $this->getActiveAssignment($student);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'No active internship assignment'
            ], 404);
        }

        $attendances = Attendance::where('assignment_id', $assignment->id)
            ->when($request->month, function($q, $month) {
                $q->whereMonth('date', $month);
            })
            ->when($request->year, function($q, $year) {
                $q->whereYear('date', $year);
            })
            ->when($request->status, function($q, $status) {
                $q->where('status', $status);
            })
            ->orderBy('date', 'desc')
            ->paginate($request->per_page ?? 31);

        return response()->json([
            'success' => true,
            'data' => $attendances
        ]);
    }

    /**
     * Get attendance statistics
     */
    public function statistics(Request $request)
    {
        $student = $request->user()->student;
        $assignment = $this->getActiveAssignment($student);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'No active internship assignment'
            ], 404);
        }

        $month = $request->month ?? Carbon::now()->month;
        $year = $request->year ?? Carbon::now()->year;

        $stats = [
            'total_days' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->count(),
            'present' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->where('status', 'present')->count(),
            'late' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->where('status', 'late')->count(),
            'early_leave' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->where('status', 'early_leave')->count(),
            'not_clocked_out' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->where('status', 'not_clocked_out')->count(),
            'permission' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->where('status', 'permission')->count(),
            'absent' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->where('status', 'absent')->count(),
            'attendance_rate' => 0,
        ];

        // Calculate attendance rate
        $totalWorkingDays = $stats['total_days'];
        $totalPresent = $stats['present'] + $stats['late'];
        $stats['attendance_rate'] = $totalWorkingDays > 0
            ? round(($totalPresent / $totalWorkingDays) * 100, 2)
            : 0;

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Helper: Get active assignment
     */
    private function getActiveAssignment($student)
    {
        return InternshipAssignment::where('student_id', $student->id)
            ->where('status', 'active')
            ->with('company')
            ->first();
    }

    /**
     * Helper: Check if today is working day
     */
    private function isWorkingDay($company, $date)
    {
        $dayName = strtolower($date->format('l')); // monday, tuesday, etc
        $workingDays = json_decode($company->working_days, true) ??
            ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

        return in_array($dayName, $workingDays);
    }
}
