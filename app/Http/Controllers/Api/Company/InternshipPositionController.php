<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\InternshipPosition;
use Illuminate\Http\Request;

class InternshipPositionController extends Controller
{
    /**
     * Get company_id from authenticated user
     */
    private function getCompanyId(Request $request)
    {
        return $request->user()->company_id;
    }

    /**
     * Display a listing of positions
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

        $positions = InternshipPosition::where('company_id', $companyId)
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->withCount('internshipApplications')
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $positions
        ]);
    }

    /**
     * Store a newly created position
     */
    public function store(Request $request)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'quota' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'sometimes|in:open,closed',
        ]);

        $position = InternshipPosition::create([
            'company_id' => $companyId,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'quota' => $validated['quota'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'] ?? 'open',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Position created successfully',
            'data' => $position
        ], 201);
    }

    /**
     * Display the specified position
     */
    public function show(Request $request, $id)
    {
        // For company viewing their own position
        if ($request->user()->role === 'company') {
            $companyId = $this->getCompanyId($request);

            $position = InternshipPosition::where('company_id', $companyId)
                ->where('id', $id)
                ->withCount('internshipApplications')
                ->with(['internshipApplications.student.user'])
                ->firstOrFail();
        } else {
            // For students browsing positions
            $position = InternshipPosition::where('id', $id)
                ->where('status', 'open')
                ->with('company')
                ->withCount('internshipApplications')
                ->firstOrFail();
        }

        return response()->json([
            'success' => true,
            'data' => $position
        ]);
    }

    /**
     * Update the specified position
     */
    public function update(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $position = InternshipPosition::where('company_id', $companyId)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'quota' => 'sometimes|integer|min:1',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'status' => 'sometimes|in:open,closed',
        ]);

        $position->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Position updated successfully',
            'data' => $position
        ]);
    }

    /**
     * Remove the specified position
     */
    public function destroy(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $position = InternshipPosition::where('company_id', $companyId)
            ->where('id', $id)
            ->firstOrFail();

        // Check if there are active applications
        $hasActiveApplications = $position->internshipApplications()
            ->whereIn('status', ['submitted', 'approved_school', 'approved_company'])
            ->exists();

        if ($hasActiveApplications) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete position with active applications'
            ], 422);
        }

        $position->delete();

        return response()->json([
            'success' => true,
            'message' => 'Position deleted successfully'
        ]);
    }

    /**
     * Browse positions (for students)
     */
    public function browse(Request $request)
    {
        $positions = InternshipPosition::where('status', 'open')
            ->where('end_date', '>=', now())
            ->with('company')
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('company', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            })
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->withCount('internshipApplications')
            ->latest()
            ->paginate($request->per_page ?? 12);

        return response()->json([
            'success' => true,
            'data' => $positions
        ]);
    }

    /**
     * Toggle position status (open/closed)
     */
    public function toggleStatus(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found'
            ], 404);
        }

        $position = InternshipPosition::where('company_id', $companyId)
            ->where('id', $id)
            ->firstOrFail();

        $newStatus = $position->status === 'open' ? 'closed' : 'open';
        $position->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => "Position status changed to {$newStatus}",
            'data' => $position
        ]);
    }
}
