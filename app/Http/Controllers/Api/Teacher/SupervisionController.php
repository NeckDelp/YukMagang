<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\InternshipAssignment;
use App\Models\Company;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupervisionController extends Controller
{
    /**
     * Get all assignments supervised by this teacher
     * With advanced filtering and grouping
     */
    public function myAssignments(Request $request)
    {
        $teacher = $request->user()->teacher;

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher profile not found'
            ], 404);
        }

        $assignments = InternshipAssignment::with([
            'student.user',
            'student' => function($q) {
                $q->select('id', 'user_id', 'school_id', 'nis', 'class', 'major', 'year');
            },
            'company',
            'school'
        ])
            ->where('supervisor_teacher_id', $teacher->id)
            ->when($request->company_id, fn($q, $companyId) =>
                $q->where('company_id', $companyId)
            )
            ->when($request->major, fn($q, $major) =>
                $q->whereHas('student', fn($sq) => $sq->where('major', $major))
            )
            ->when($request->status, fn($q, $status) =>
                $q->where('status', $status)
            )
            ->when($request->search, function($query, $search) {
                $query->whereHas('student.user', fn($q) =>
                    $q->where('name', 'like', "%{$search}%")
                )->orWhereHas('company', fn($q) =>
                    $q->where('name', 'like', "%{$search}%")
                );
            })
            ->withCount('dailyReports')
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $assignments
        ]);
    }

    /**
     * Get assignments grouped by company
     * This shows: "PT ABC: 5 students, PT XYZ: 3 students"
     */
    public function groupedByCompany(Request $request)
    {
        $teacher = $request->user()->teacher;

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher profile not found'
            ], 404);
        }

        $grouped = InternshipAssignment::with(['company', 'student.user', 'student'])
            ->where('supervisor_teacher_id', $teacher->id)
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->major, fn($q, $major) =>
                $q->whereHas('student', fn($sq) => $sq->where('major', $major))
            )
            ->get()
            ->groupBy('company_id')
            ->map(function($assignments, $companyId) {
                $company = $assignments->first()->company;
                $students = $assignments->map(function($assignment) {
                    return [
                        'assignment_id' => $assignment->id,
                        'student_id' => $assignment->student->id,
                        'nis' => $assignment->student->nis,
                        'name' => $assignment->student->user->name,
                        'major' => $assignment->student->major,
                        'class' => $assignment->student->class,
                        'status' => $assignment->status,
                        'start_date' => $assignment->start_date?->format('Y-m-d'),
                        'end_date' => $assignment->end_date?->format('Y-m-d'),
                        'daily_reports_count' => $assignment->dailyReports()->count(),
                    ];
                });

                return [
                    'company' => [
                        'id' => $company->id,
                        'name' => $company->name,
                        'industry' => $company->industry,
                        'address' => $company->address,
                    ],
                    'total_students' => $students->count(),
                    'students' => $students->values(),
                    'majors' => $students->pluck('major')->unique()->values(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $grouped
        ]);
    }

    /**
     * Get assignments grouped by major (jurusan)
     * This shows: "RPL: 10 students in 3 companies"
     */
    public function groupedByMajor(Request $request)
    {
        $teacher = $request->user()->teacher;

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher profile not found'
            ], 404);
        }

        $grouped = InternshipAssignment::with(['student.user', 'student', 'company'])
            ->where('supervisor_teacher_id', $teacher->id)
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->get()
            ->groupBy(fn($assignment) => $assignment->student->major)
            ->map(function($assignments, $major) {
                // Group by company within this major
                $byCompany = $assignments->groupBy('company_id')->map(function($companyAssignments) {
                    $company = $companyAssignments->first()->company;
                    return [
                        'company' => [
                            'id' => $company->id,
                            'name' => $company->name,
                        ],
                        'students_count' => $companyAssignments->count(),
                        'students' => $companyAssignments->map(fn($a) => [
                            'id' => $a->student->id,
                            'name' => $a->student->user->name,
                            'nis' => $a->student->nis,
                        ])->values()
                    ];
                })->values();

                return [
                    'major' => $major,
                    'total_students' => $assignments->count(),
                    'companies_count' => $assignments->pluck('company_id')->unique()->count(),
                    'companies' => $byCompany,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $grouped
        ]);
    }

    /**
     * Get specific company's students under my supervision
     */
    public function companyStudents(Request $request, $companyId)
    {
        $teacher = $request->user()->teacher;

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher profile not found'
            ], 404);
        }

        $company = Company::findOrFail($companyId);

        $students = InternshipAssignment::with([
            'student.user',
            'student',
            'dailyReports' => fn($q) => $q->latest()->limit(5)
        ])
            ->where('supervisor_teacher_id', $teacher->id)
            ->where('company_id', $companyId)
            ->when($request->major, fn($q, $major) =>
                $q->whereHas('student', fn($sq) => $sq->where('major', $major))
            )
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->withCount('dailyReports')
            ->get()
            ->map(function($assignment) {
                return [
                    'assignment_id' => $assignment->id,
                    'student' => [
                        'id' => $assignment->student->id,
                        'nis' => $assignment->student->nis,
                        'name' => $assignment->student->user->name,
                        'email' => $assignment->student->user->email,
                        'phone' => $assignment->student->user->phone,
                        'major' => $assignment->student->major,
                        'class' => $assignment->student->class,
                    ],
                    'start_date' => $assignment->start_date?->format('Y-m-d'),
                    'end_date' => $assignment->end_date?->format('Y-m-d'),
                    'status' => $assignment->status,
                    'daily_reports_count' => $assignment->daily_reports_count,
                    'recent_reports' => $assignment->dailyReports->map(fn($r) => [
                        'id' => $r->id,
                        'date' => $r->date?->format('Y-m-d'),
                        'status' => $r->status,
                    ]),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'company' => $company,
                'total_students' => $students->count(),
                'students' => $students
            ]
        ]);
    }

    /**
     * Dashboard for teacher
     */
    public function dashboard(Request $request)
    {
        $teacher = $request->user()->teacher;

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher profile not found'
            ], 404);
        }

        // Total statistics
        $totalAssignments = InternshipAssignment::where('supervisor_teacher_id', $teacher->id)->count();
        $activeAssignments = InternshipAssignment::where('supervisor_teacher_id', $teacher->id)
            ->where('status', 'active')->count();

        // Pending daily reports
        $pendingReports = DB::table('daily_reports')
            ->join('internship_assignments', 'daily_reports.assignment_id', '=', 'internship_assignments.id')
            ->where('internship_assignments.supervisor_teacher_id', $teacher->id)
            ->where('daily_reports.status', 'pending')
            ->count();

        // Breakdown by major
        $byMajor = InternshipAssignment::with('student')
            ->where('supervisor_teacher_id', $teacher->id)
            ->get()
            ->groupBy(fn($a) => $a->student->major)
            ->map(fn($items) => $items->count());

        // Breakdown by company
        $byCompany = InternshipAssignment::with('company')
            ->where('supervisor_teacher_id', $teacher->id)
            ->where('status', 'active')
            ->get()
            ->groupBy('company_id')
            ->map(function($items) {
                return [
                    'company' => $items->first()->company->name,
                    'students_count' => $items->count()
                ];
            })
            ->values();

        // Recent assignments
        $recentAssignments = InternshipAssignment::with(['student.user', 'company'])
            ->where('supervisor_teacher_id', $teacher->id)
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'statistics' => [
                    'total_assignments' => $totalAssignments,
                    'active_assignments' => $activeAssignments,
                    'pending_reports' => $pendingReports,
                ],
                'breakdown_by_major' => $byMajor,
                'breakdown_by_company' => $byCompany,
                'recent_assignments' => $recentAssignments,
            ]
        ]);
    }

    /**
     * Bulk approve daily reports for a company group
     */
    public function bulkApproveDailyReports(Request $request)
    {
        $validated = $request->validate([
            'report_ids' => 'required|array',
            'report_ids.*' => 'exists:daily_reports,id'
        ]);

        $teacher = $request->user()->teacher;

        // Verify all reports belong to teacher's supervised students
        $reports = DB::table('daily_reports')
            ->join('internship_assignments', 'daily_reports.assignment_id', '=', 'internship_assignments.id')
            ->whereIn('daily_reports.id', $validated['report_ids'])
            ->where('internship_assignments.supervisor_teacher_id', $teacher->id)
            ->where('daily_reports.status', 'pending')
            ->pluck('daily_reports.id');

        if ($reports->count() !== count($validated['report_ids'])) {
            return response()->json([
                'success' => false,
                'message' => 'Some reports are not under your supervision or already processed'
            ], 403);
        }

        // Bulk update
        DB::table('daily_reports')
            ->whereIn('id', $reports)
            ->update([
                'status' => 'approved',
                'updated_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => count($reports) . ' reports approved successfully'
        ]);
    }
}
