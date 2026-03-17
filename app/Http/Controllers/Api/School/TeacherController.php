<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeacherController extends Controller
{
    /**
     * Display a listing of teachers
     */
    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $teachers = Teacher::with(['user', 'school'])
            ->where('school_id', $schoolId)
            ->when($request->search, function ($query, $search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                })->orWhere('nip', 'like', "%{$search}%");
            })
            ->when($request->position, fn($q, $position) => $q->where('position', $position))
            ->withCount('supervisedAssignments')
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $teachers
        ]);
    }

    /**
     * Store a newly created teacher
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:255',
            'nip' => 'required|string|unique:teachers,nip',
            'position' => 'required|string|max:255',
            'expertise_majors' => 'nullable|array',
            'expertise_majors.*' => 'string|max:255',
        ]);

        $schoolId = $request->user()->school_id;

        DB::beginTransaction();
        try {
            // Create user first
            $user = User::create([
                'school_id' => $schoolId,
                'role' => 'teacher',
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'] ?? null,
                'is_active' => true,
            ]);

            // Create teacher profile
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'school_id' => $schoolId,
                'nip' => $validated['nip'],
                'position' => $validated['position'],
                'expertise_majors' => $validated['expertise_majors'] ?? null,
            ]);

            DB::commit();

            $teacher->load(['user', 'school']);

            return response()->json([
                'success' => true,
                'message' => 'Teacher created successfully',
                'data' => $teacher
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create teacher: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified teacher
     */
    public function show(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;

        $teacher = Teacher::with([
            'user',
            'school',
            'supervisedAssignments.student.user',
            'supervisedAssignments.company'
        ])
            ->where('school_id', $schoolId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $teacher
        ]);
    }

    /**
     * Update the specified teacher
     */
    public function update(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;

        $teacher = Teacher::where('school_id', $schoolId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $teacher->user_id,
            'phone' => 'nullable|string|max:255',
            'nip' => 'sometimes|string|unique:teachers,nip,' . $teacher->id,
            'position' => 'sometimes|string|max:255',
            'expertise_majors' => 'sometimes|nullable|array',
            'expertise_majors.*' => 'string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        DB::beginTransaction();
        try {
            // Update user data
            if (isset($validated['name']) || isset($validated['email']) || isset($validated['phone']) || isset($validated['is_active'])) {
                $teacher->user->update([
                    'name' => $validated['name'] ?? $teacher->user->name,
                    'email' => $validated['email'] ?? $teacher->user->email,
                    'phone' => $validated['phone'] ?? $teacher->user->phone,
                    'is_active' => $validated['is_active'] ?? $teacher->user->is_active,
                ]);
            }

            // Update teacher profile
            $teacher->update([
                'nip' => $validated['nip'] ?? $teacher->nip,
                'position' => $validated['position'] ?? $teacher->position,
                'expertise_majors' => array_key_exists('expertise_majors', $validated)
                    ? $validated['expertise_majors']
                    : $teacher->expertise_majors,
            ]);

            DB::commit();

            $teacher->load(['user', 'school']);

            return response()->json([
                'success' => true,
                'message' => 'Teacher updated successfully',
                'data' => $teacher
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update teacher: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified teacher
     */
    public function destroy(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;

        $teacher = Teacher::where('school_id', $schoolId)->findOrFail($id);

        // Check if teacher has active supervised assignments
        $hasActiveAssignments = $teacher->supervisedAssignments()
            ->where('status', 'active')->exists();

        if ($hasActiveAssignments) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete teacher with active supervised assignments'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $user = $teacher->user;
            $teacher->delete();
            $user->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Teacher deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete teacher: ' . $e->getMessage()
            ], 500);
        }
    }
}
