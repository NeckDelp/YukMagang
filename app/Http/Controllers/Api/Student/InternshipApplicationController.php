<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\InternshipApplication;
use App\Models\InternshipPosition;
use App\Models\InternshipAssignment;
use App\Models\SchoolCompanyPartnership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Enums\ApplicationStatus;

class InternshipApplicationController extends Controller
{
    /**
     * Display a listing of student's applications
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

        $applications = InternshipApplication::where('student_id', $student->id)
            ->with(['company', 'position', 'school'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $applications
        ]);
    }

    /**
     * Store a newly created application
     */
    public function store(Request $request)
    {
        $student = $request->user()->student;

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student profile not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'position_id' => 'required|exists:internship_positions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if student already has active assignment
        $hasActiveAssignment = InternshipAssignment::where('student_id', $student->id)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveAssignment) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active internship assignment'
            ], 422);
        }

        $position = InternshipPosition::findOrFail($request->position_id);

        // Check if position is still open
        if ($position->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => 'This position is no longer available'
            ], 422);
        }

        // Check if student already applied to this position
        $existingApplication = InternshipApplication::where('student_id', $student->id)
            ->where('position_id', $request->position_id)
            ->whereIn('status', [ApplicationStatus::SUBMITTED, ApplicationStatus::APPROVED_SCHOOL, ApplicationStatus::APPROVED_COMPANY])
            ->exists();

        if ($existingApplication) {
            return response()->json([
                'success' => false,
                'message' => 'You have already applied to this position'
            ], 422);
        }

        // Check quota
        $acceptedCount = InternshipApplication::where('position_id', $request->position_id)
            ->where('status', ApplicationStatus::APPROVED_COMPANY)
            ->count();

        if ($acceptedCount >= $position->quota) {
            return response()->json([
                'success' => false,
                'message' => 'This position has reached its quota'
            ], 422);
        }

        // Handle CV file upload
        $cvPath = null;
        if ($request->hasFile('cv_file')) {
            $cvPath = $request->file('cv_file')->store('cvs', 'public');
        }

        $application = InternshipApplication::create([
            'student_id'   => $student->id,
            'school_id'    => $student->school_id,
            'company_id'   => $position->company_id,
            'position_id'  => $request->position_id,
            'cv_file'      => $cvPath,
            'cover_letter' => $request->cover_letter,
            'status'       => ApplicationStatus::SUBMITTED,
            'applied_at'   => now(),
        ]);

        $application->load(['company', 'position', 'school']);

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully',
            'data'    => $application
        ], 201);
    }

    /**
     * Display the specified application
     */
    public function show(Request $request, $id)
    {
        $student = $request->user()->student;

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student profile not found'
            ], 404);
        }

        $application = InternshipApplication::where('student_id', $student->id)
            ->where('id', $id)
            ->with(['company', 'position', 'school'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $application
        ]);
    }

    /**
     * Remove the specified application
     * Only allowed if status is 'submitted'
     */
    public function destroy(Request $request, $id)
    {
        $student = $request->user()->student;

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student profile not found'
            ], 404);
        }

        $application = InternshipApplication::where('student_id', $student->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($application->status !== ApplicationStatus::SUBMITTED) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel application that has been processed'
            ], 422);
        }

        $application->delete();

        return response()->json([
            'success' => true,
            'message' => 'Application cancelled successfully'
        ]);
    }

    /**
     * For school to view all applications
     */
    public function indexForSchool(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $applications = InternshipApplication::where('school_id', $schoolId)
            ->with(['student.user', 'company', 'position'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->student_id, fn($q, $id) => $q->where('student_id', $id))
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $applications
        ]);
    }

    /**
     * For school admin to see detail of one application (including CV url)
     */
    public function showForSchool(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;

        $application = InternshipApplication::where('school_id', $schoolId)
            ->where('id', $id)
            ->with(['student.user', 'company', 'position', 'schoolDecisionBy', 'companyDecisionBy'])
            ->firstOrFail();

        // Generate public URL for CV file if it exists
        $data = $application->toArray();
        if ($application->cv_file) {
            $data['cv_url'] = asset('storage/' . $application->cv_file);
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
