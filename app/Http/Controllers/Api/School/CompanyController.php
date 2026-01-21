<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies
     */
    public function index(Request $request)
    {
        $companies = Company::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('industry', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($request->industry, fn($q, $industry) => $q->where('industry', $industry))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->withCount(['internshipPositions', 'internshipAssignments'])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $companies
        ]);
    }

    /**
     * Store a newly created company
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'industry' => 'required|string|max:255',
            'address' => 'required|string',
            'description' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('companies/logos', 'public');
        }

        $company = Company::create([
            'name' => $validated['name'],
            'industry' => $validated['industry'],
            'address' => $validated['address'],
            'description' => $validated['description'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'website' => $validated['website'] ?? null,
            'logo' => $logoPath,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Company created successfully',
            'data' => $company
        ], 201);
    }

    /**
     * Display the specified company
     */
    public function show($id)
    {
        $company = Company::with([
            'internshipPositions' => fn($q) => $q->where('status', 'open'),
            'internshipAssignments.student.user'
        ])
            ->withCount([
                'internshipPositions',
                'internshipAssignments',
                'internshipApplications'
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $company
        ]);
    }

    /**
     * Update the specified company
     */
    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'industry' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'description' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'status' => 'sometimes|in:active,inactive',
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
            'message' => 'Company updated successfully',
            'data' => $company
        ]);
    }

    /**
     * Remove the specified company
     */
    public function destroy($id)
    {
        $company = Company::findOrFail($id);

        // Check if company has active assignments
        $hasActiveAssignments = $company->internshipAssignments()
            ->where('status', 'active')->exists();

        if ($hasActiveAssignments) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete company with active internship assignments'
            ], 422);
        }

        // Delete logo
        if ($company->logo) {
            Storage::disk('public')->delete($company->logo);
        }

        $company->delete();

        return response()->json([
            'success' => true,
            'message' => 'Company deleted successfully'
        ]);
    }

    /**
     * Browse companies (for students)
     */
    public function browse(Request $request)
    {
        $companies = Company::where('status', 'active')
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('industry', 'like', "%{$search}%");
            })
            ->when($request->industry, fn($q, $industry) => $q->where('industry', $industry))
            ->withCount([
                'internshipPositions' => fn($q) => $q->where('status', 'open')
            ])
            ->latest()
            ->paginate($request->per_page ?? 12);

        return response()->json([
            'success' => true,
            'data' => $companies
        ]);
    }
}
