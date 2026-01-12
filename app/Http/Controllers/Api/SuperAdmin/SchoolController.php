<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SchoolController extends Controller
{
    /**
     * Display a listing of schools
     */
    public function index(Request $request)
    {
        $schools = School::query()
            ->withCount(['users', 'students', 'teachers', 'internshipAssignments'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('npsn', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $schools
        ]);
    }

    /**
     * Store a newly created school
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'npsn' => 'required|string|unique:schools,npsn',
            'address' => 'required|string',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('schools/logos', 'public');
        }

        $school = School::create([
            'name' => $validated['name'],
            'npsn' => $validated['npsn'],
            'address' => $validated['address'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'logo' => $logoPath,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'School created successfully',
            'data' => $school
        ], 201);
    }

    /**
     * Display the specified school
     */
    public function show($id)
    {
        $school = School::withCount([
            'users',
            'students',
            'teachers',
            'internshipAssignments',
            'internshipApplications'
        ])->findOrFail($id);

        // Get additional statistics
        $school->active_assignments = $school->internshipAssignments()
            ->where('status', 'active')->count();
        $school->completed_assignments = $school->internshipAssignments()
            ->where('status', 'completed')->count();

        return response()->json([
            'success' => true,
            'data' => $school
        ]);
    }

    /**
     * Update the specified school
     */
    public function update(Request $request, $id)
    {
        $school = School::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'npsn' => 'sometimes|string|unique:schools,npsn,' . $id,
            'address' => 'sometimes|string',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($school->logo) {
                Storage::disk('public')->delete($school->logo);
            }
            $validated['logo'] = $request->file('logo')->store('schools/logos', 'public');
        }

        $school->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'School updated successfully',
            'data' => $school
        ]);
    }

    /**
     * Remove the specified school
     */
    public function destroy($id)
    {
        $school = School::findOrFail($id);

        // Check if school has active assignments
        $hasActiveAssignments = $school->internshipAssignments()
            ->where('status', 'active')->exists();

        if ($hasActiveAssignments) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete school with active internship assignments'
            ], 422);
        }

        // Delete logo
        if ($school->logo) {
            Storage::disk('public')->delete($school->logo);
        }

        $school->delete();

        return response()->json([
            'success' => true,
            'message' => 'School deleted successfully'
        ]);
    }

    /**
     * Get platform statistics
     */
    public function statistics()
    {
        $stats = [
            'total_schools' => School::count(),
            'active_schools' => School::where('status', 'active')->count(),
            'total_students' => \App\Models\Student::count(),
            'total_teachers' => \App\Models\Teacher::count(),
            'total_companies' => \App\Models\Company::count(),
            'total_assignments' => \App\Models\InternshipAssignment::count(),
            'active_assignments' => \App\Models\InternshipAssignment::where('status', 'active')->count(),
            'total_applications' => \App\Models\InternshipApplication::count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
