<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\InternshipApplication;
use App\Models\InternshipPosition;
use App\Models\InternshipAssignment;
use Illuminate\Http\Request;

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

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'position_id' => 'required|exists:internship_positions,id',
        ]);

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

        // Check if position is still open
        $position = InternshipPosition::where('id', $validated['position_id'])
            ->where('company_id', $validated['company_id'])
            ->where('status', 'open')
            ->first();

        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'This position is no longer available'
            ], 422);
        }

        // Check if student already applied to this position
        $existingApplication = InternshipApplication::where('student_id', $student->id)
            ->where('position_id', $validated['position_id'])
            ->whereIn('status', ['submitted', 'approved_school', 'approved_company'])
            ->exists();

        if ($existingApplication) {
            return response()->json([
                'success' => false,
                'message' => 'You have already applied to this position'
            ], 422);
        }

        // Check quota
        $acceptedCount = InternshipApplication::where('position_id', $validated['position_id'])
            ->where('status', 'approved_company')
            ->count();

        if ($acceptedCount >= $position->quota) {
            return response()->json([
                'success' => false,
                'message' => 'This position has reached its quota'
            ], 422);
        }

        $application = InternshipApplication::create([
            'student_id' => $student->id,
            'school_id' => $student->school_id,
            'company_id' => $validated['company_id'],
            'position_id' => $validated['position_id'],
            'status' => 'submitted',
            'applied_at' => now(),
        ]);

        $application->load(['company', 'position', 'school']);

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully',
            'data' => $application
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

        if ($application->status !== 'submitted') {
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
}
