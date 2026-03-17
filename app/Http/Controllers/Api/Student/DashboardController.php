<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\TaskSubmission;
use App\Models\InternshipAssignment;
use App\Models\InternshipPosition;
use App\Models\SchoolCompanyPartnership;
use App\Models\Permission;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard data
     * Auto-detect active/inactive student
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

        // Get active assignment
        $assignment = $student->internshipAssignments()
            ->where('status', 'active')
            ->with(['company', 'position', 'supervisorTeacher.user'])
            ->first();

        if ($assignment) {
            // ACTIVE STUDENT DASHBOARD
            return $this->getActiveDashboard($student, $assignment);
        } else {
            // INACTIVE STUDENT DASHBOARD
            return $this->getInactiveDashboard($student);
        }
    }

    /**
     * Dashboard for ACTIVE student (sedang PKL)
     */
    private function getActiveDashboard($student, $assignment)
    {
        $today = Carbon::today();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        // 1. PKL STATUS INFO
        $pklStatus = [
            'is_active' => true,
            'student_name' => $student->user->name,
            'nis' => $student->nis,
            'class' => $student->class,
            'major' => $student->major,
            'school_name' => $student->school->name,
            'company_name' => $assignment->company->name,
            'company_address' => $assignment->company->address . ', ' . $assignment->company->city,
            'position' => $assignment->position->title ?? 'Intern',
            'supervisor_teacher' => $assignment->supervisorTeacher
                ? $assignment->supervisorTeacher->user->name
                : 'Belum ditentukan',
            'start_date' => $assignment->start_date,
            'end_date' => $assignment->end_date,
            'duration_days' => Carbon::parse($assignment->start_date)
                ->diffInDays(Carbon::parse($assignment->end_date)),
            'days_passed' => Carbon::parse($assignment->start_date)
                ->diffInDays(Carbon::today()),
            'days_remaining' => Carbon::today()
                ->diffInDays(Carbon::parse($assignment->end_date)),
        ];

        // 2. ATTENDANCE SUMMARY (bulan ini)
        $attendanceStats = [
            'total_days' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->count(),
            'present' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->where('status', 'present')->count(),
            'late' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->where('status', 'late')->count(),
            'permission' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->where('status', 'permission')->count(),
            'absent' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->where('status', 'absent')->count(),
            'early_leave' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear)
                ->where('status', 'early_leave')->count(),
        ];

        // Calculate attendance rate
        $totalPresent = $attendanceStats['present'] + $attendanceStats['late'];
        $attendanceStats['attendance_rate'] = $attendanceStats['total_days'] > 0
            ? round(($totalPresent / $attendanceStats['total_days']) * 100, 2)
            : 0;

        // 3. TASK SUMMARY
        $taskRecipientIds = DB::table('task_recipients')
            ->where('student_id', $student->id)
            ->pluck('task_id');

        $taskStats = [
            'total' => $taskRecipientIds->count(),
            'completed' => TaskSubmission::whereIn('task_id', $taskRecipientIds)
                ->where('student_id', $student->id)
                ->where('status', 'approved')
                ->count(),
            'submitted' => TaskSubmission::whereIn('task_id', $taskRecipientIds)
                ->where('student_id', $student->id)
                ->where('status', 'submitted')
                ->count(),
            'in_progress' => TaskSubmission::whereIn('task_id', $taskRecipientIds)
                ->where('student_id', $student->id)
                ->where('status', 'in_progress')
                ->count(),
            'not_submitted' => $taskRecipientIds->count() - TaskSubmission::whereIn('task_id', $taskRecipientIds)
                ->where('student_id', $student->id)
                ->count(),
        ];

        // 4. TODAY'S ATTENDANCE
        $todayAttendance = Attendance::where('assignment_id', $assignment->id)
            ->where('date', $today)
            ->first();

        // 5. ATTENDANCE CHART DATA (30 hari terakhir)
        $attendanceChart = $this->getAttendanceChartData($assignment->id);

        // 6. RECENT ACTIVITIES (10 terakhir)
        $recentActivities = $this->getRecentActivities($student->id, $assignment->id);

        // 7. RECENT TASKS (5 tugas terbaru)
        $recentTasks = Task::whereIn('id', $taskRecipientIds)
            ->with(['taskSubmissions' => function($query) use ($student) {
                $query->where('student_id', $student->id);
            }])
            ->orderBy('deadline', 'desc')
            ->limit(5)
            ->get()
            ->map(function($task) use ($student) {
                $submission = $task->taskSubmissions->first();
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'deadline' => $task->deadline,
                    'is_overdue' => Carbon::parse($task->deadline)->isPast(),
                    'status' => $submission ? $submission->status : 'not_submitted',
                    'submitted_at' => $submission ? $submission->submitted_at : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'has_active_internship' => true,
                'pkl_status' => $pklStatus,
                'attendance_summary' => $attendanceStats,
                'task_summary' => $taskStats,
                'today_attendance' => $todayAttendance,
                'attendance_chart' => $attendanceChart,
                'recent_activities' => $recentActivities,
                'recent_tasks' => $recentTasks,
            ]
        ]);
    }

    /**
     * Dashboard for INACTIVE student (tidak sedang PKL)
     */
    private function getInactiveDashboard($student)
    {
        // 1. PKL STATUS
        $pklStatus = [
            'is_active' => false,
            'student_name' => $student->user->name,
            'nis' => $student->nis,
            'class' => $student->class,
            'major' => $student->major,
            'school_name' => $student->school->name,
            'message' => 'Anda saat ini tidak sedang PKL',
        ];

        // 2. PAST PKL SUMMARY
        $pastAssignments = InternshipAssignment::where('student_id', $student->id)
            ->where('status', 'completed')
            ->with(['company', 'position'])
            ->orderBy('end_date', 'desc')
            ->get()
            ->map(function($assignment) {
                return [
                    'id' => $assignment->id,
                    'company' => $assignment->company->name,
                    'position' => $assignment->position->title ?? 'Intern',
                    'start_date' => $assignment->start_date,
                    'end_date' => $assignment->end_date,
                    'duration_days' => Carbon::parse($assignment->start_date)
                        ->diffInDays(Carbon::parse($assignment->end_date)),
                ];
            });

        // 3. AVAILABLE JOB VACANCIES
        $partneredCompanyIds = SchoolCompanyPartnership::where('school_id', $student->school_id)
            ->where('status', 'active')
            ->pluck('company_id');

        $availableVacancies = InternshipPosition::whereIn('company_id', $partneredCompanyIds)
            ->where('status', 'open')
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', Carbon::now());
            })
            ->with('company')
            ->limit(5)
            ->get()
            ->map(function($position) {
                $acceptedCount = $position->internshipApplications()
                    ->where('status', 'approved_company')
                    ->count();

                return [
                    'id' => $position->id,
                    'title' => $position->title,
                    'company' => $position->company->name,
                    'location' => $position->company->city,
                    'quota' => $position->quota,
                    'remaining_quota' => $position->quota - $acceptedCount,
                ];
            });

        // 4. MY APPLICATIONS STATUS
        $myApplications = $student->internshipApplications()
            ->with(['position.company'])
            ->orderBy('applied_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($app) {
                return [
                    'id' => $app->id,
                    'position' => $app->position->title,
                    'company' => $app->position->company->name,
                    'status' => $app->status,
                    'applied_at' => $app->applied_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'has_active_internship' => false,
                'pkl_status' => $pklStatus,
                'past_internships' => $pastAssignments,
                'available_vacancies' => $availableVacancies,
                'my_applications' => $myApplications,
                'suggestions' => [
                    'message' => 'Silakan melamar posisi PKL yang tersedia',
                    'action' => 'Lihat Lowongan'
                ]
            ]
        ]);
    }

    /**
     * Get attendance chart data for last 30 days
     */
    private function getAttendanceChartData($assignmentId)
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $attendances = Attendance::where('assignment_id', $assignmentId)
            ->where('date', '>=', $thirtyDaysAgo)
            ->orderBy('date', 'asc')
            ->get();

        $chartData = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Hadir',
                    'data' => [],
                    'color' => '#10b981' // green
                ],
                [
                    'label' => 'Terlambat',
                    'data' => [],
                    'color' => '#f59e0b' // yellow
                ],
                [
                    'label' => 'Izin',
                    'data' => [],
                    'color' => '#3b82f6' // blue
                ],
                [
                    'label' => 'Alpa',
                    'data' => [],
                    'color' => '#ef4444' // red
                ],
            ]
        ];

        // Fill last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $chartData['labels'][] = $date->format('d M');

            $attendance = $attendances->firstWhere('date', $date->format('Y-m-d'));

            $chartData['datasets'][0]['data'][] = ($attendance && $attendance->status === 'present') ? 1 : 0;
            $chartData['datasets'][1]['data'][] = ($attendance && $attendance->status === 'late') ? 1 : 0;
            $chartData['datasets'][2]['data'][] = ($attendance && $attendance->status === 'permission') ? 1 : 0;
            $chartData['datasets'][3]['data'][] = ($attendance && $attendance->status === 'absent') ? 1 : 0;
        }

        return $chartData;
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities($studentId, $assignmentId)
    {
        $activities = collect();

        // Recent Attendances (5)
        $recentAttendances = Attendance::where('assignment_id', $assignmentId)
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get()
            ->map(function($att) {
                return [
                    'type' => 'attendance',
                    'title' => 'Presensi',
                    'description' => 'Status: ' . ucfirst(str_replace('_', ' ', $att->status)),
                    'date' => $att->date,
                    'time' => $att->clock_in_time,
                    'icon' => 'check-circle',
                    'status' => $att->status,
                    'created_at' => $att->created_at,
                ];
            });

        // Recent Task Submissions (5)
        $recentSubmissions = TaskSubmission::where('student_id', $studentId)
            ->with('task')
            ->orderBy('submitted_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($sub) {
                return [
                    'type' => 'task',
                    'title' => 'Tugas: ' . $sub->task->title,
                    'description' => 'Status: ' . ucfirst(str_replace('_', ' ', $sub->status)),
                    'date' => Carbon::parse($sub->submitted_at)->format('Y-m-d'),
                    'time' => Carbon::parse($sub->submitted_at)->format('H:i'),
                    'icon' => 'file-text',
                    'status' => $sub->status,
                    'created_at' => $sub->created_at,
                ];
            });

        // Recent Permissions (3)
        $recentPermissions = Permission::where('assignment_id', $assignmentId)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(function($perm) {
                return [
                    'type' => 'permission',
                    'title' => 'Izin ' . ucfirst($perm->type),
                    'description' => 'Status: ' . ucfirst($perm->status),
                    'date' => $perm->permission_date,
                    'time' => Carbon::parse($perm->created_at)->format('H:i'),
                    'icon' => 'alert-circle',
                    'status' => $perm->status,
                    'created_at' => $perm->created_at,
                ];
            });

        // Merge and sort by created_at
        $activities = $activities
            ->merge($recentAttendances)
            ->merge($recentSubmissions)
            ->merge($recentPermissions)
            ->sortByDesc('created_at')
            ->take(10)
            ->values();

        return $activities;
    }
}
