<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Http\Resources\InternshipAssignmentResource;
use App\Models\InternshipAssignment;
use Illuminate\Http\Request;

class InternshipAssignmentController extends Controller
{
    /**
     * Display a listing of internship assignments
     */
    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $assignments = InternshipAssignment::with([
            'student.user',
            'company',
            'supervisorTeacher.user',
            'school'
        ])
            ->where('school_id', $schoolId)
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->student_id, fn($q, $studentId) => $q->where('student_id', $studentId))
            ->when($request->company_id, fn($q, $companyId) => $q->where('company_id', $companyId))
            ->when($request->search, function ($query, $search) {
                $query->whereHas('student.user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })->orWhereHas('company', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($request->per_page ?? 15);

        return InternshipAssignmentResource::collection($assignments);
    }

    /**
     * Store a newly created assignment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'company_id' => 'required|exists:companies,id',
            'supervisor_teacher_id' => 'required|exists:teachers,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'sometimes|in:active,completed,cancelled',
        ]);

        $schoolId = $request->user()->school_id;

        // Verify student belongs to school
        $student = \App\Models\Student::where('id', $validated['student_id'])
            ->where('school_id', $schoolId)
            ->firstOrFail();

        // Verify teacher belongs to school
        $teacher = \App\Models\Teacher::where('id', $validated['supervisor_teacher_id'])
            ->where('school_id', $schoolId)
            ->firstOrFail();

        // Check if student already has active assignment
        $existingActive = InternshipAssignment::where('student_id', $validated['student_id'])
            ->where('status', 'active')
            ->exists();

        if ($existingActive) {
            return response()->json([
                'success' => false,
                'message' => 'Student already has an active internship assignment'
            ], 422);
        }

        $assignment = InternshipAssignment::create([
            'student_id' => $validated['student_id'],
            'school_id' => $schoolId,
            'company_id' => $validated['company_id'],
            'supervisor_teacher_id' => $validated['supervisor_teacher_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'] ?? 'active',
        ]);

        $assignment->load([
            'student.user',
            'company',
            'supervisorTeacher.user',
            'school'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Internship assignment created successfully',
            'data' => new InternshipAssignmentResource($assignment)
        ], 201);
    }

    /**
     * Display the specified assignment
     */
    public function show(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;

        $assignment = InternshipAssignment::with([
            'student.user',
            'company',
            'supervisorTeacher.user',
            'school',
            'dailyReports',
            'assessments'
        ])
            ->where('school_id', $schoolId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new InternshipAssignmentResource($assignment)
        ]);
    }

    /**
     * Update the specified assignment
     */
    public function update(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;

        $assignment = InternshipAssignment::where('school_id', $schoolId)
            ->findOrFail($id);

        $validated = $request->validate([
            'company_id' => 'sometimes|exists:companies,id',
            'supervisor_teacher_id' => 'sometimes|exists:teachers,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'status' => 'sometimes|in:active,completed,cancelled',
        ]);

        // Verify teacher belongs to school if updated
        if (isset($validated['supervisor_teacher_id'])) {
            \App\Models\Teacher::where('id', $validated['supervisor_teacher_id'])
                ->where('school_id', $schoolId)
                ->firstOrFail();
        }

        $assignment->update($validated);

        $assignment->load([
            'student.user',
            'company',
            'supervisorTeacher.user',
            'school'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Internship assignment updated successfully',
            'data' => new InternshipAssignmentResource($assignment)
        ]);
    }

    /**
     * Remove the specified assignment
     */
    public function destroy(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;

        $assignment = InternshipAssignment::where('school_id', $schoolId)
            ->findOrFail($id);

        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Internship assignment deleted successfully'
        ]);
    }

    /**
     * Get assignment statistics for dashboard
     */
    public function statistics(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $stats = [
            'total_assignments' => InternshipAssignment::where('school_id', $schoolId)->count(),
            'active_assignments' => InternshipAssignment::where('school_id', $schoolId)
                ->where('status', 'active')->count(),
            'completed_assignments' => InternshipAssignment::where('school_id', $schoolId)
                ->where('status', 'completed')->count(),
            'cancelled_assignments' => InternshipAssignment::where('school_id', $schoolId)
                ->where('status', 'cancelled')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
