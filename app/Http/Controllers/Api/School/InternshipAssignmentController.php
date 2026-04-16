<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Models\InternshipAssignment;
use App\Models\InternshipApplication;
use App\Models\InternshipPosition;
use App\Models\Student;
use App\Models\SchoolCompanyPartnership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternshipAssignmentController extends Controller
{
    /**
     * Display a listing of assignments for the school
     */
    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;
        
        $assignments = InternshipAssignment::where('school_id', $schoolId)
            ->when($request->user()->role === 'teacher', function($q) use ($request) {
                // If user is a teacher, limit assignments to those they supervise
                $teacherId = $request->user()->teacher->id;
                return $q->where('supervisor_teacher_id', $teacherId);
            })
            ->when($request->student_id, function($q, $studentId) {
                $q->where('student_id', $studentId);
            })
            ->when($request->status, function($q, $status) {
                $q->where('status', $status);
            })
            ->when($request->search, function($q, $search) {
                $q->whereHas('student.user', function($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                })->orWhereHas('student', function($query) use ($search) {
                    $query->where('nis', 'like', "%{$search}%");
                })->orWhereHas('company', function($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                });
            })
            ->with(['student.user', 'company', 'supervisorTeacher.user', 'companySupervisor'])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $assignments
        ]);
    }

    /**
     * Get students for bulk apply
     * Menampilkan format: [Nama] - [Kelas]
     */
    public function getStudentsForBulkApply(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $students = Student::where('school_id', $schoolId)
            ->with('user')
            ->when($request->class, function($q, $class) {
                $q->where('class', $class);
            })
            ->when($request->major, function($q, $major) {
                $q->where('major', $major);
            })
            ->when($request->year, function($q, $year) {
                $q->where('year', $year);
            })
            ->when($request->search, function($q, $search) {
                $q->whereHas('user', function($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                })
                ->orWhere('nis', 'like', "%{$search}%");
            })
            // HANYA siswa yang TIDAK punya active assignment
            ->whereDoesntHave('internshipAssignments', function($q) {
                $q->where('status', 'active');
            })
            ->orderBy('class')
            ->orderBy('id')
            ->get()
            ->map(function($student) {
                return [
                    'id' => $student->id,
                    'name' => $student->user->name,
                    'nis' => $student->nis,
                    'class' => $student->class,
                    'major' => $student->major,
                    'year' => $student->year,
                    'label' => $student->user->name . ' - ' . $student->class, // Format: [Nama] - [Kelas]
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $students,
            'total' => $students->count()
        ]);
    }

    /**
     * Get unique classes for filter
     */
    public function getClasses(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $classes = Student::where('school_id', $schoolId)
            ->distinct()
            ->pluck('class')
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $classes
        ]);
    }

    /**
     * BULK APPLY - Admin sekolah mendaftarkan banyak siswa sekaligus
     * Sesuai dengan gambar Form Penugasan Siswa
     */
    public function bulkApply(Request $request)
    {
        $validated = $request->validate([
            'position_id' => 'required|exists:internship_positions,id',
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        $schoolId = $request->user()->school_id;
        $position = InternshipPosition::findOrFail($validated['position_id']);

        // Verify position is from partnered company
        $isPartnered = SchoolCompanyPartnership::where('school_id', $schoolId)
            ->where('company_id', $position->company_id)
            ->where('status', 'active')
            ->exists();

        if (!$isPartnered) {
            return response()->json([
                'success' => false,
                'message' => 'This company is not partnered with your school'
            ], 403);
        }

        // Verify all students belong to this school
        $students = Student::whereIn('id', $validated['student_ids'])
            ->where('school_id', $schoolId)
            ->with('user')
            ->get();

        if ($students->count() !== count($validated['student_ids'])) {
            return response()->json([
                'success' => false,
                'message' => 'Some students do not belong to your school'
            ], 422);
        }

        $created = [];
        $skipped = [];

        DB::transaction(function() use ($students, $position, $request, $schoolId, &$created, &$skipped) {
            foreach ($students as $student) {
                // Check if student already has active assignment
                $hasActive = InternshipAssignment::where('student_id', $student->id)
                    ->where('status', 'active')
                    ->exists();

                if ($hasActive) {
                    $skipped[] = [
                        'student_id' => $student->id,
                        'name' => $student->user->name,
                        'class' => $student->class,
                        'reason' => 'Already has active internship'
                    ];
                    continue;
                }

                // Check if already applied to this position
                $existing = InternshipApplication::where('student_id', $student->id)
                    ->where('position_id', $position->id)
                    ->whereIn('status', ['submitted', 'approved_school', 'approved_company'])
                    ->exists();

                if ($existing) {
                    $skipped[] = [
                        'student_id' => $student->id,
                        'name' => $student->user->name,
                        'class' => $student->class,
                        'reason' => 'Already applied to this position'
                    ];
                    continue;
                }

                // Create application (WITHOUT CV)
                $application = InternshipApplication::create([
                    'student_id' => $student->id,
                    'school_id' => $schoolId,
                    'company_id' => $position->company_id,
                    'position_id' => $position->id,
                    'cv_file' => null, // NO CV
                    'status' => 'submitted',
                    'applied_at' => now(),
                    'created_by' => $request->user()->id,
                    'created_by_type' => 'admin', // Admin bulk apply
                ]);

                $created[] = [
                    'student_id' => $student->id,
                    'name' => $student->user->name,
                    'class' => $student->class,
                    'nis' => $student->nis,
                    'application_id' => $application->id,
                    'status' => 'submitted',
                ];
            }
        });

        return response()->json([
            'success' => true,
            'message' => count($created) . ' students successfully applied. Waiting for teacher approval.',
            'data' => [
                'created' => $created,
                'skipped' => $skipped,
                'summary' => [
                    'total_selected' => count($validated['student_ids']),
                    'successfully_applied' => count($created),
                    'skipped' => count($skipped),
                    'position' => [
                        'id' => $position->id,
                        'title' => $position->title,
                        'company' => $position->company->name,
                    ]
                ]
            ]
        ], 201);
    }

    /**
     * Create actual assignment after approved_company
     * Called manually by admin or automatically after company approval
     */
    public function createFromApplication(Request $request)
    {
        $validated = $request->validate([
            'application_id' => 'required|exists:internship_applications,id',
            'supervisor_teacher_id' => 'nullable|exists:teachers,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        $schoolId = $request->user()->school_id;
        $application = InternshipApplication::where('id', $validated['application_id'])
            ->where('school_id', $schoolId)
            ->with(['student', 'position', 'company'])
            ->firstOrFail();

        // Must be approved by company first
        if ($application->status !== 'approved_company') {
            return response()->json([
                'success' => false,
                'message' => 'Application must be approved by company first. Current status: ' . $application->status
            ], 422);
        }

        // Check if assignment already exists
        $existingAssignment = InternshipAssignment::where('student_id', $application->student_id)
            ->where('company_id', $application->company_id)
            ->where('position_id', $application->position_id)
            ->first();

        if ($existingAssignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment already exists for this student'
            ], 422);
        }

        // Create assignment
        $assignment = InternshipAssignment::create([
            'student_id' => $application->student_id,
            'company_id' => $application->company_id,
            'position_id' => $application->position_id,
            'supervisor_teacher_id' => $validated['supervisor_teacher_id'] ?? null,
            'start_date' => $validated['start_date'] ?? $application->position->start_date,
            'end_date' => $validated['end_date'] ?? $application->position->end_date,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Internship assignment created successfully. Student is now active.',
            'data' => $assignment->load(['student.user', 'company', 'position'])
        ], 201);
    }

    /**
     * Bulk create assignments from multiple approved applications
     */
    public function bulkCreateAssignments(Request $request)
    {
        $validated = $request->validate([
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'exists:internship_applications,id',
            'supervisor_teacher_id' => 'nullable|exists:teachers,id',
        ]);

        $schoolId = $request->user()->school_id;
        $created = [];
        $skipped = [];

        DB::transaction(function() use ($validated, $schoolId, $request, &$created, &$skipped) {
            foreach ($validated['application_ids'] as $appId) {
                $application = InternshipApplication::where('id', $appId)
                    ->where('school_id', $schoolId)
                    ->with(['student.user', 'position'])
                    ->first();

                if (!$application) {
                    continue;
                }

                if ($application->status !== 'approved_company') {
                    $skipped[] = [
                        'application_id' => $appId,
                        'student' => $application->student->user->name,
                        'reason' => 'Not approved by company yet'
                    ];
                    continue;
                }

                // Check if assignment already exists
                $existingAssignment = InternshipAssignment::where('student_id', $application->student_id)
                    ->where('status', 'active')
                    ->exists();

                if ($existingAssignment) {
                    $skipped[] = [
                        'application_id' => $appId,
                        'student' => $application->student->user->name,
                        'reason' => 'Already has active assignment'
                    ];
                    continue;
                }

                // Create assignment
                $assignment = InternshipAssignment::create([
                    'student_id' => $application->student_id,
                    'company_id' => $application->company_id,
                    'position_id' => $application->position_id,
                    'supervisor_teacher_id' => $validated['supervisor_teacher_id'] ?? null,
                    'start_date' => $application->position->start_date,
                    'end_date' => $application->position->end_date,
                    'status' => 'active',
                ]);

                $created[] = [
                    'assignment_id' => $assignment->id,
                    'student' => $application->student->user->name,
                    'company' => $application->company->name,
                ];
            }
        });

        return response()->json([
            'success' => true,
            'message' => count($created) . ' assignments created',
            'data' => [
                'created' => $created,
                'skipped' => $skipped,
            ]
        ]);
    }
    /**
     * Assign or update supervisor teacher on an existing assignment
     * Called by school admin after company has approved the application
     */
    public function assignTeacher(Request $request, $id)
    {
        $validated = $request->validate([
            'supervisor_teacher_id' => 'required|exists:teachers,id',
        ]);

        $schoolId = $request->user()->school_id;

        $assignment = InternshipAssignment::where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        $assignment->update([
            'supervisor_teacher_id' => $validated['supervisor_teacher_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Teacher supervisor assigned successfully',
            'data' => $assignment->load(['student.user', 'supervisorTeacher.user', 'company'])
        ]);
    }

    /**
     * Assign or update company supervisor (mentor) on an existing assignment
     * Called by school admin to manage/override company supervisor
     */
    public function assignMentor(Request $request, $id)
    {
        $validated = $request->validate([
            'company_supervisor_id' => 'required|exists:company_supervisors,id',
        ]);

        $schoolId = $request->user()->school_id;

        $assignment = InternshipAssignment::where('id', $id)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        // Verify that the supervisor belongs to the same company as the assignment
        $supervisor = \App\Models\CompanySupervisor::findOrFail($validated['company_supervisor_id']);
        if ($supervisor->company_id !== $assignment->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'The selected mentor does not belong to the correct company'
            ], 422);
        }

        $assignment->update([
            'company_supervisor_id' => $validated['company_supervisor_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Company mentor assigned successfully',
            'data' => $assignment->load(['student.user', 'companySupervisor', 'company'])
        ]);
    }
}
