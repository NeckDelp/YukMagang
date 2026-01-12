<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    /**
     * Display a listing of students
     */
    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $students = Student::with(['user', 'school'])
            ->where('school_id', $schoolId)
            ->when($request->search, function ($query, $search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                })->orWhere('nis', 'like', "%{$search}%");
            })
            ->when($request->class, fn($q, $class) => $q->where('class', $class))
            ->when($request->major, fn($q, $major) => $q->where('major', $major))
            ->when($request->year, fn($q, $year) => $q->where('year', $year))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return StudentResource::collection($students);
    }

    /**
     * Store a newly created student
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:255',
            'nis' => 'required|string|unique:students,nis',
            'class' => 'required|string|max:255',
            'major' => 'required|string|max:255',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $schoolId = $request->user()->school_id;

        DB::beginTransaction();
        try {
            // Create user first
            $user = User::create([
                'school_id' => $schoolId,
                'role' => 'student',
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'] ?? null,
                'is_active' => true,
            ]);

            // Create student profile
            $student = Student::create([
                'user_id' => $user->id,
                'school_id' => $schoolId,
                'nis' => $validated['nis'],
                'class' => $validated['class'],
                'major' => $validated['major'],
                'year' => $validated['year'],
            ]);

            DB::commit();

            $student->load(['user', 'school']);

            return response()->json([
                'success' => true,
                'message' => 'Student created successfully',
                'data' => new StudentResource($student)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create student: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified student
     */
    public function show(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;

        $student = Student::with(['user', 'school', 'internshipAssignments.company'])
            ->where('school_id', $schoolId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new StudentResource($student)
        ]);
    }

    /**
     * Update the specified student
     */
    public function update(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;

        $student = Student::where('school_id', $schoolId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $student->user_id,
            'phone' => 'nullable|string|max:255',
            'nis' => 'sometimes|string|unique:students,nis,' . $student->id,
            'class' => 'sometimes|string|max:255',
            'major' => 'sometimes|string|max:255',
            'year' => 'sometimes|integer|min:2000|max:2100',
            'is_active' => 'sometimes|boolean',
        ]);

        DB::beginTransaction();
        try {
            // Update user data
            if (isset($validated['name']) || isset($validated['email']) || isset($validated['phone']) || isset($validated['is_active'])) {
                $student->user->update([
                    'name' => $validated['name'] ?? $student->user->name,
                    'email' => $validated['email'] ?? $student->user->email,
                    'phone' => $validated['phone'] ?? $student->user->phone,
                    'is_active' => $validated['is_active'] ?? $student->user->is_active,
                ]);
            }

            // Update student profile
            $student->update([
                'nis' => $validated['nis'] ?? $student->nis,
                'class' => $validated['class'] ?? $student->class,
                'major' => $validated['major'] ?? $student->major,
                'year' => $validated['year'] ?? $student->year,
            ]);

            DB::commit();

            $student->load(['user', 'school']);

            return response()->json([
                'success' => true,
                'message' => 'Student updated successfully',
                'data' => new StudentResource($student)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update student: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified student
     */
    public function destroy(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;

        $student = Student::where('school_id', $schoolId)->findOrFail($id);

        DB::beginTransaction();
        try {
            // Delete student and user
            $user = $student->user;
            $student->delete();
            $user->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete student: ' . $e->getMessage()
            ], 500);
        }
    }
}
