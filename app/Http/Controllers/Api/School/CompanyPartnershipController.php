<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SchoolCompanyPartnership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompanyPartnershipController extends Controller
{
    /**
     * Get available companies that haven't been partnered yet
     */
    public function available(Request $request)
    {
        $user = Auth::user();
        $schoolId = $user->school_id;

        // Get companies that are NOT yet partnered with this school
        $partneredCompanyIds = SchoolCompanyPartnership::where('school_id', $schoolId)
            ->pluck('company_id');

        $companies = Company::whereNotIn('id', $partneredCompanyIds)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('industry', 'like', "%{$search}%")
                      ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->when($request->industry, function ($query, $industry) {
                $query->where('industry', $industry);
            })
            ->when($request->city, function ($query, $city) {
                $query->where('city', $city);
            })
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => 'success',
            'message' => 'Available companies retrieved successfully',
            'data' => $companies
        ]);
    }

    /**
     * Get companies that are already partnered
     */
    public function partnered(Request $request)
    {
        $user = Auth::user();
        $schoolId = $user->school_id;

        $partnerships = SchoolCompanyPartnership::where('school_id', $schoolId)
            ->with('company')
            ->when($request->search, function ($query, $search) {
                $query->whereHas('company', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('industry', 'like', "%{$search}%")
                      ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => 'success',
            'message' => 'Partnered companies retrieved successfully',
            'data' => $partnerships
        ]);
    }

    /**
     * Partner with a company (like "follow" a company)
     */
    public function partner($id)
    {
        $user = Auth::user();
        $schoolId = $user->school_id;

        // Check if company exists
        $company = Company::findOrFail($id);

        // Check if already partnered
        $exists = SchoolCompanyPartnership::where('school_id', $schoolId)
            ->where('company_id', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Already partnered with this company'
            ], 422);
        }

        // Create partnership
        $partnership = SchoolCompanyPartnership::create([
            'school_id' => $schoolId,
            'company_id' => $id,
            'status' => 'active', // Langsung active tanpa approval
            'partnered_at' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully partnered with company',
            'data' => $partnership->load('company')
        ], 201);
    }

    /**
     * Remove partnership with a company (unfollow)
     */
    public function unpartner($id)
    {
        $user = Auth::user();
        $schoolId = $user->school_id;

        $partnership = SchoolCompanyPartnership::where('school_id', $schoolId)
            ->where('company_id', $id)
            ->firstOrFail();

        $partnership->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully removed partnership with company'
        ]);
    }
}
