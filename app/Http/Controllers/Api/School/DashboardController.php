<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Models\InternshipAssignment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\InternshipApplication;
use App\Models\DailyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics and data
     */
    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;

        // Basic counts
        $activeAssignments = InternshipAssignment::where('school_id', $schoolId)
            ->where('status', 'active')->count();
        $completedAssignments = InternshipAssignment::where('school_id', $schoolId)
            ->where('status', 'completed')->count();
        $totalCompanies = InternshipAssignment::where('school_id', $schoolId)
            ->distinct('company_id')->count('company_id');

        $stats = [
            'total_students'       => Student::where('school_id', $schoolId)->count(),
            'total_teachers'       => Teacher::where('school_id', $schoolId)->count(),

            // Aliases used by the frontend dashboard
            'total_companies'      => $totalCompanies,
            'active_internships'   => $activeAssignments,
            'completed_internships'=> $completedAssignments,

            // Assignment statistics
            'total_assignments'    => InternshipAssignment::where('school_id', $schoolId)->count(),
            'active_assignments'   => $activeAssignments,
            'completed_assignments'=> $completedAssignments,
            'cancelled_assignments'=> InternshipAssignment::where('school_id', $schoolId)
                ->where('status', 'cancelled')->count(),

            // Application statistics
            'total_applications'   => InternshipApplication::where('school_id', $schoolId)->count(),
            'pending_applications' => InternshipApplication::where('school_id', $schoolId)
                ->where('status', 'submitted')->count(),
            'approved_applications'=> InternshipApplication::where('school_id', $schoolId)
                ->where('status', 'approved_school')->count(),

            // Daily report statistics
            'pending_reports'      => DailyReport::whereHas('assignment', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })->where('status', 'pending')->count(),

            'approved_reports'     => DailyReport::whereHas('assignment', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })->where('status', 'approved')->count(),
        ];

        // Recent assignments (last 5)
        $recentAssignments = InternshipAssignment::with([
            'student.user',
            'company',
            'supervisorTeacher.user'
        ])
            ->where('school_id', $schoolId)
            ->latest()
            ->limit(5)
            ->get();

        // Assignment by status breakdown
        $assignmentsByStatus = InternshipAssignment::where('school_id', $schoolId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        // Students by major breakdown
        $studentsByMajor = Student::where('school_id', $schoolId)
            ->select('major', DB::raw('count(*) as count'))
            ->groupBy('major')
            ->get();

        // Assignments by company (top 5)
        $assignmentsByCompany = InternshipAssignment::where('school_id', $schoolId)
            ->select('company_id', DB::raw('count(*) as count'))
            ->with('company:id,name')
            ->groupBy('company_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // Monthly assignment trend (last 6 months)
        $monthlyTrend = InternshipAssignment::where('school_id', $schoolId)
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Pending daily reports (need review)
        $pendingReports = DailyReport::whereHas('assignment', function ($q) use ($schoolId) {
            $q->where('school_id', $schoolId);
        })
            ->with(['assignment.student.user', 'assignment.company'])
            ->where('status', 'pending')
            ->latest()
            ->limit(5)
            ->get();

        // Pending applications (need school approval)
        $pendingApplicationsList = InternshipApplication::where('school_id', $schoolId)
            ->with(['student.user', 'company'])
            ->where('status', 'submitted')
            ->latest()
            ->limit(5)
            ->get();

        // Recent placements — formatted for frontend
        $recentPlacements = $recentAssignments->map(function ($a) {
            return [
                'id'               => $a->id,
                'student_name'     => $a->student?->user?->name ?? '-',
                'student_class'    => $a->student?->class ?? '-',
                'company_name'     => $a->company?->name ?? '-',
                'supervisor_teacher' => $a->supervisorTeacher?->user?->name ?? '-',
                'start_date'       => $a->start_date ?? '-',
                'end_date'         => $a->end_date ?? '-',
                'status'           => $a->status ?? 'active',
            ];
        });

        // Attention items — combine pending reports and pending applications
        $rawItems = collect();
        foreach ($pendingReports as $r) {
            $rawItems->push([
                'title'       => $r->assignment?->student?->user?->name ?? 'Siswa',
                'description' => 'Laporan harian menunggu review — ' . ($r->assignment?->company?->name ?? '-'),
                'time'        => $r->created_at?->diffForHumans() ?? '-',
                'type'        => 'pending_report',
                'created_at'  => $r->created_at,
            ]);
        }
        foreach ($pendingApplicationsList as $a) {
            $rawItems->push([
                'title'       => $a->student?->user?->name ?? 'Siswa',
                'description' => 'Lamaran PKL perlu persetujuan — ' . ($a->company?->name ?? '-'),
                'time'        => $a->created_at?->diffForHumans() ?? '-',
                'type'        => 'pending_application',
                'created_at'  => $a->created_at,
            ]);
        }
        
        $attentionItems = $rawItems->sortByDesc('created_at')->values()->map(function($item) {
            unset($item['created_at']);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'statistics'           => $stats,
                'recent_placements'    => $recentPlacements,
                'attention_items'      => $attentionItems,
                'recent_assignments'   => $recentAssignments,
                'assignments_by_status'=> $assignmentsByStatus,
                'students_by_major'    => $studentsByMajor,
                'assignments_by_company' => $assignmentsByCompany,
                'monthly_trend'        => $monthlyTrend,
                'pending_reports'      => $pendingReports,
            ]
        ]);
    }

    /**
     * Get summary for specific student
     */
    public function studentSummary(Request $request, $studentId)
    {
        $schoolId = $request->user()->school_id;

        $student = Student::where('school_id', $schoolId)
            ->where('id', $studentId)
            ->with('user')
            ->firstOrFail();

        $summary = [
            'student' => $student,
            'total_assignments' => InternshipAssignment::where('student_id', $studentId)->count(),
            'active_assignment' => InternshipAssignment::where('student_id', $studentId)
                ->where('status', 'active')
                ->with(['company', 'supervisorTeacher.user'])
                ->first(),
            'total_applications' => InternshipApplication::where('student_id', $studentId)->count(),
            'daily_reports_count' => DailyReport::whereHas('assignment', function ($q) use ($studentId) {
                $q->where('student_id', $studentId);
            })->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }
}
