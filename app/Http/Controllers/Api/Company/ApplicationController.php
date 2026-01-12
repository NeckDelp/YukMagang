<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\InternshipApplication;
use App\Models\InternshipAssignment;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    /**
     * Get company_id from authenticated user
     */
    private function getCompanyId(Request $request)
    {
        return $request->user()->company_id;
    }

    /**
     * Display a listing of applications
     */
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $applications = InternshipApplication::where('company_id', $companyId)
            ->with(['student.user', 'position', 'school'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->position_id, fn($q, $id) => $q->where('position_id', $id))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $applications
        ]);
    }

    /**
     * Display the specified application
     */
    public function show(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $application = InternshipApplication::where('company_id', $companyId)
            ->where('id', $id)
            ->with([
                'student.user',
                'student.school',
                'position',
                'school'
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $application
        ]);
    }

    /**
     * Approve application
     */
    public function approve(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $application = InternshipApplication::where('company_id', $companyId)
            ->where('id', $id)
            ->with('position')
            ->firstOrFail();

        // Check if already approved
        if ($application->status === 'approved_company') {
            return response()->json([
                'success' => false,
                'message' => 'Application already approved'
            ], 422);
        }

        // Check quota
        $acceptedCount = InternshipApplication::where('position_id', $application->position_id)
            ->where('status', 'approved_company')
            ->count();

        if ($acceptedCount >= $application->position->quota) {
            return response()->json([
                'success' => false,
                'message' => 'Position quota has been reached'
            ], 422);
        }

        $application->update(['status' => 'approved_company']);

        return response()->json([
            'success' => true,
            'message' => 'Application approved successfully',
            'data' => $application
        ]);
    }

    /**
     * Reject application
     */
    public function reject(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $application = InternshipApplication::where('company_id', $companyId)
            ->where('id', $id)
            ->firstOrFail();

        // Check if already processed
        if (in_array($application->status, ['approved_company', 'rejected_company'])) {
            return response()->json([
                'success' => false,
                'message' => 'Application has already been processed'
            ], 422);
        }

        $application->update(['status' => 'rejected_company']);

        return response()->json([
            'success' => true,
            'message' => 'Application rejected',
            'data' => $application
        ]);
    }

    /**
     * Get current interns
     */
    public function currentInterns(Request $request)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $interns = InternshipAssignment::where('company_id', $companyId)
            ->where('status', 'active')
            ->with([
                'student.user',
                'student.school',
                'supervisorTeacher.user'
            ])
            ->withCount('dailyReports')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $interns
        ]);
    }

    /**
     * Get company profile
     */
    public function profile(Request $request)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $company = Company::withCount([
            'internshipPositions',
            'internshipApplications',
            'internshipAssignments'
        ])->findOrFail($companyId);

        return response()->json([
            'success' => true,
            'data' => $company
        ]);
    }

    /**
     * Update company profile
     */
    public function updateProfile(Request $request)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $company = Company::findOrFail($companyId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'industry' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'description' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $validated['logo'] = $request->file('logo')->store('companies/logos', 'public');
        }

        $company->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Company profile updated successfully',
            'data' => $company
        ]);
    }

    /**
     * Get application statistics
     */
    public function statistics(Request $request)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $stats = [
            'total_positions' => \App\Models\InternshipPosition::where('company_id', $companyId)->count(),
            'open_positions' => \App\Models\InternshipPosition::where('company_id', $companyId)
                ->where('status', 'open')->count(),
            'total_applications' => InternshipApplication::where('company_id', $companyId)->count(),
            'pending_applications' => InternshipApplication::where('company_id', $companyId)
                ->whereIn('status', ['submitted', 'approved_school'])->count(),
            'approved_applications' => InternshipApplication::where('company_id', $companyId)
                ->where('status', 'approved_company')->count(),
            'rejected_applications' => InternshipApplication::where('company_id', $companyId)
                ->where('status', 'rejected_company')->count(),
            'current_interns' => InternshipAssignment::where('company_id', $companyId)
                ->where('status', 'active')->count(),
            'completed_interns' => InternshipAssignment::where('company_id', $companyId)
                ->where('status', 'completed')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
