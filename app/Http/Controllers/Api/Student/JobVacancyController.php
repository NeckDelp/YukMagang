<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\InternshipPosition;
use App\Models\InternshipApplication;
use App\Models\InternshipAssignment;
use App\Models\SchoolCompanyPartnership;
use Illuminate\Http\Request;

class JobVacancyController extends Controller
{
    /**
     * Get all vacancies (from partnered companies)
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

        // Check if student already has active assignment
        $hasActiveAssignment = InternshipAssignment::where('student_id', $student->id)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveAssignment) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active internship'
            ], 422);
        }

        // Get partnered companies
        $partneredCompanyIds = SchoolCompanyPartnership::where('school_id', $student->school_id)
            ->where('status', 'active')
            ->pluck('company_id');

        // Get open positions
        $positions = InternshipPosition::whereIn('company_id', $partneredCompanyIds)
            ->where('status', 'open')
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->with('company')
            ->when($request->search, function($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%")
                          ->orWhereHas('company', function ($companyQuery) use ($search) {
                              $companyQuery->where('name', 'like', "%{$search}%");
                          });
                });
            })
            ->when($request->company_id, function ($query, $companyId) {
                $query->where('company_id', $companyId);
            })
            ->when($request->location, function ($query, $location) {
                $query->whereHas('company', function ($companyQuery) use ($location) {
                    $companyQuery->where('city', 'like', "%{$location}%");
                });
            })
            ->get()
            ->map(function($position) use ($student) {
                // Count accepted applications
                $acceptedCount = InternshipApplication::where('position_id', $position->id)
                    ->where('status', 'approved_company')
                    ->count();

                $remainingQuota = $position->quota - $acceptedCount;

                // Check if student already applied
                $alreadyApplied = InternshipApplication::where('student_id', $student->id)
                    ->where('position_id', $position->id)
                    ->whereIn('status', ['submitted', 'approved_school', 'approved_company'])
                    ->exists();

                return [
                    'id' => $position->id,
                    'title' => $position->title,
                    'company' => [
                        'id' => $position->company->id,
                        'name' => $position->company->name,
                        'logo' => $position->company->logo ?? null,
                        'industry' => $position->company->industry ?? null,
                    ],
                    'location' => ($position->company->city ?? '') . ', ' . ($position->company->province ?? ''),
                    'description' => $position->description,
                    'start_date' => $position->start_date,
                    'end_date' => $position->end_date,
                    'quota' => $position->quota,
                    'accepted_count' => $acceptedCount,
                    'remaining_quota' => $remainingQuota,
                    'is_full' => $remainingQuota <= 0,
                    'status' => $remainingQuota > 0 ? 'active' : 'full',
                    'already_applied' => $alreadyApplied,
                    'created_at' => $position->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $positions
        ]);
    }

    /**
     * Get vacancy detail
     */
    public function show($id)
    {
        $student = request()->user()->student;

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student profile not found'
            ], 404);
        }

        // Check if student is currently active
        $hasActiveAssignment = InternshipAssignment::where('student_id', $student->id)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveAssignment) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active internship'
            ], 403);
        }

        $position = InternshipPosition::with('company')->findOrFail($id);

        // Check if partnered
        $isPartnered = SchoolCompanyPartnership::where('school_id', $student->school_id)
            ->where('company_id', $position->company_id)
            ->where('status', 'active')
            ->exists();

        if (!$isPartnered) {
            return response()->json([
                'success' => false,
                'message' => 'This company is not partnered with your school'
            ], 403);
        }

        // Count accepted applications
        $acceptedCount = InternshipApplication::where('position_id', $position->id)
            ->where('status', 'approved_company')
            ->count();

        $remainingQuota = $position->quota - $acceptedCount;

        // Check if already applied
        $application = InternshipApplication::where('student_id', $student->id)
            ->where('position_id', $id)
            ->whereIn('status', ['submitted', 'approved_school', 'approved_company'])
            ->first();

        $detail = [
            'id' => $position->id,
            'title' => $position->title,
            'company' => [
                'id' => $position->company->id,
                'name' => $position->company->name,
                'logo' => $position->company->logo ?? null,
                'industry' => $position->company->industry ?? null,
                'address' => $position->company->address ?? null,
                'city' => $position->company->city ?? null,
                'province' => $position->company->province ?? null,
                'phone' => $position->company->phone ?? null,
                'email' => $position->company->email ?? null,
                'website' => $position->company->website ?? null,
                'description' => $position->company->description ?? null,
            ],
            'location' => ($position->company->city ?? '') . ', ' . ($position->company->province ?? ''),
            'description' => $position->description,
            'requirements' => $position->requirements ?? null,
            'responsibilities' => $position->responsibilities ?? null,
            'benefits' => $position->benefits ?? null,
            'start_date' => $position->start_date,
            'end_date' => $position->end_date,
            'quota' => $position->quota,
            'accepted_count' => $acceptedCount,
            'remaining_quota' => $remainingQuota,
            'is_full' => $remainingQuota <= 0,
            'status' => $remainingQuota > 0 && $position->status === 'open' ? 'active' : 'full',
            'already_applied' => !is_null($application),
            'application_status' => $application ? $application->status : null,
            'created_at' => $position->created_at,
            'updated_at' => $position->updated_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $detail
        ]);
    }
}
