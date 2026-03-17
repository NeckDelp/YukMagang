<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login user and create token
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact administrator.'
            ], 403);
        }

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Load relationships based on role
        $user->load('school');

        if ($user->role === 'student') {
            $user->load('student');
        } elseif ($user->role === 'teacher' || $user->role === 'school_admin') {
            $user->load('teacher');
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    /**
     * Register new user (for super_admin only via separate endpoint)
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'role' => 'required|in:super_admin,school_admin,teacher,student,company',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:255',
        ]);

        // Only super_admin can be created without school_id
        if ($validated['role'] !== 'super_admin' && empty($validated['school_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'School ID is required for this role'
            ], 422);
        }

        $user = User::create([
            'school_id' => $validated['school_id'] ?? null,
            'role' => $validated['role'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'is_active' => true,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

    /**
     * Register a new school (Step 1 of school registration)
     */
    public function registerSchool(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'npsn' => 'required|string|unique:schools,npsn',
            'address' => 'required|string',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $school = School::create([
            'name' => $validated['name'],
            'npsn' => $validated['npsn'],
            'address' => $validated['address'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'School registered successfully',
            'data' => $school
        ], 201);
    }

    /**
     * Register a school admin account (Step 2 of school registration)
     */
    public function registerSchoolAdmin(Request $request)
    {
        $validated = $request->validate([
            'school_id' => 'required|exists:schools,id',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:255',
        ]);

        $school = School::findOrFail($validated['school_id']);

        // Check if this school already has an admin
        $existingAdmin = User::where('school_id', $school->id)
            ->where('role', 'school_admin')
            ->first();

        if ($existingAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Sekolah ini sudah memiliki akun admin.'
            ], 422);
        }

        $user = User::create([
            'school_id' => $school->id,
            'role' => 'school_admin',
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'is_active' => true,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'School admin registered successfully',
            'data' => [
                'user' => $user->load('school'),
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('school');

        if ($user->role === 'student') {
            $user->load('student');
        } elseif ($user->role === 'teacher' || $user->role === 'school_admin') {
            $user->load('teacher');
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Revoke all tokens
     */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'All sessions logged out successfully'
        ]);
    }
}
