<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\InternshipApplication;
use App\Models\TeacherCompanySupervision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Enums\ApplicationStatus;

class ApplicationApprovalController extends Controller
{
    /**
     * Get pending applications for teacher to review
     * Hanya aplikasi dari perusahaan yang dibimbing teacher ini
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

        // Get companies that this teacher supervises
        $companyIds = TeacherCompanySupervision::where('teacher_id', $teacher->id)
            ->pluck('company_id');

        $applications = InternshipApplication::whereIn('company_id', $companyIds)
            ->where('school_id', $teacher->school_id)
            ->where('status', ApplicationStatus::SUBMITTED)
            ->with([
                'student.user',
                'position.company',
                'school'
            ])
            ->orderBy('applied_at', 'asc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $applications,
            'meta' => [
                'pending_count' => $applications->total(),
            ]
        ]);
    }

    /**
     * Get all applications (pending + approved + rejected)
     */
    public function index(Request $request)
    {
        $teacher = $request->user()->teacher;

        $companyIds = TeacherCompanySupervision::where('teacher_id', $teacher->id)
            ->pluck('company_id');

        $applications = InternshipApplication::whereIn('company_id', $companyIds)
            ->where('school_id', $teacher->school_id)
            ->with([
                'student.user',
                'position.company',
                'school',
                'approvedBySchool'
            ])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->student_id, fn($q, $studentId) => $q->where('student_id', $studentId))
            ->orderBy('applied_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $applications
        ]);
    }

    /**
     * Get application detail
     */
    public function show($id)
    {
        $teacher = request()->user()->teacher;

        $companyIds = TeacherCompanySupervision::where('teacher_id', $teacher->id)
            ->pluck('company_id');

        $application = InternshipApplication::whereIn('company_id', $companyIds)
            ->where('id', $id)
            ->with([
                'student.user',
                'position.company',
                'school',
                'approvedBySchool',
                'approvedByCompany'
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $application
        ]);
    }

    /**
     * Approve application (Teacher - Stage 1)
     * submitted → approved_school
     */
    public function approve(Request $request, $id)
    {
        $teacher = $request->user()->teacher;

        $application = InternshipApplication::findOrFail($id);

        // Verify teacher supervises this company
        $supervises = TeacherCompanySupervision::where('teacher_id', $teacher->id)
            ->where('company_id', $application->company_id)
            ->exists();

        if (!$supervises) {
            return response()->json([
                'success' => false,
                'message' => 'You do not supervise applications for this company'
            ], 403);
        }

        if ($application->status !== ApplicationStatus::SUBMITTED) {
            return response()->json([
                'success' => false,
                'message' => 'Application already processed. Current status: ' . $application->status
            ], 422);
        }

        $validated = $request->validate([
            'school_notes' => 'nullable|string|max:500'
        ]);

        $application->update([
            'status' => ApplicationStatus::APPROVED_SCHOOL,
            'approved_by_school' => $request->user()->id,
            'approved_school_at' => now(),
            'school_notes' => $validated['school_notes'] ?? 'Disetujui oleh pembimbing sekolah',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application approved. Waiting for company approval.',
            'data' => $application->fresh([
                'student.user',
                'position.company',
                'approvedBySchool'
            ])
        ]);
    }

    /**
     * Reject application (Teacher - Stage 1)
     * submitted → rejected_school (FINAL - END)
     */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'school_notes' => 'required|string|max:500'
        ]);

        $teacher = $request->user()->teacher;
        $application = InternshipApplication::findOrFail($id);

        // Verify supervision
        $supervises = TeacherCompanySupervision::where('teacher_id', $teacher->id)
            ->where('company_id', $application->company_id)
            ->exists();

        if (!$supervises) {
            return response()->json([
                'success' => false,
                'message' => 'You do not supervise applications for this company'
            ], 403);
        }

        if ($application->status !== ApplicationStatus::SUBMITTED) {
            return response()->json([
                'success' => false,
                'message' => 'Application already processed. Current status: ' . $application->status
            ], 422);
        }

        $application->update([
            'status' => ApplicationStatus::REJECTED_SCHOOL,
            'approved_by_school' => $request->user()->id,
            'approved_school_at' => now(),
            'school_notes' => $validated['school_notes'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application rejected',
            'data' => $application->fresh([
                'student.user',
                'position.company',
                'approvedBySchool'
            ])
        ]);
    }

    /**
     * Bulk approve applications
     * Berguna untuk approve banyak siswa sekaligus yang didaftarkan oleh admin
     */
    public function bulkApprove(Request $request)
    {
        $validated = $request->validate([
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'exists:internship_applications,id',
            'school_notes' => 'nullable|string|max:500',
        ]);

        $teacher = $request->user()->teacher;
        $approved = 0;
        $skipped = [];

        DB::transaction(function() use ($validated, $teacher, $request, &$approved, &$skipped) {
            foreach ($validated['application_ids'] as $appId) {
                $app = InternshipApplication::find($appId);

                if (!$app) {
                    continue;
                }

                // Verify supervision
                $supervises = TeacherCompanySupervision::where('teacher_id', $teacher->id)
                    ->where('company_id', $app->company_id)
                    ->exists();

                if (!$supervises) {
                    $skipped[] = [
                        'application_id' => $appId,
                        'reason' => 'Not supervised by you'
                    ];
                    continue;
                }

                if ($app->status !== ApplicationStatus::SUBMITTED) {
                    $skipped[] = [
                        'application_id' => $appId,
                        'reason' => 'Already processed',
                        'current_status' => $app->status
                    ];
                    continue;
                }

                $app->update([
                    'status' => ApplicationStatus::APPROVED_SCHOOL,
                    'approved_by_school' => $request->user()->id,
                    'approved_school_at' => now(),
                    'school_notes' => $validated['school_notes'] ?? 'Bulk approved by teacher',
                ]);

                $approved++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "{$approved} applications approved successfully",
            'data' => [
                'approved_count' => $approved,
                'skipped' => $skipped,
                'total_processed' => count($validated['application_ids']),
            ]
        ]);
    }

    /**
     * Get statistics
     */
    public function statistics(Request $request)
    {
        $teacher = $request->user()->teacher;

        $companyIds = TeacherCompanySupervision::where('teacher_id', $teacher->id)
            ->pluck('company_id');

        $stats = [
            'total' => InternshipApplication::whereIn('company_id', $companyIds)
                ->where('school_id', $teacher->school_id)
                ->count(),
            'pending' => InternshipApplication::whereIn('company_id', $companyIds)
                ->where('school_id', $teacher->school_id)
                ->where('status', ApplicationStatus::SUBMITTED)
                ->count(),
            'approved' => InternshipApplication::whereIn('company_id', $companyIds)
                ->where('school_id', $teacher->school_id)
                ->where('status', ApplicationStatus::APPROVED_SCHOOL)
                ->count(),
            'rejected' => InternshipApplication::whereIn('company_id', $companyIds)
                ->where('school_id', $teacher->school_id)
                ->where('status', ApplicationStatus::REJECTED_SCHOOL)
                ->count(),
            'final_approved' => InternshipApplication::whereIn('company_id', $companyIds)
                ->where('school_id', $teacher->school_id)
                ->where('status', ApplicationStatus::APPROVED_COMPANY)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
