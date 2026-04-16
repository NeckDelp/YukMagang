<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use App\Models\Otp;
use App\Mail\OtpMail;

class AuthController extends Controller
{
    /**
     * Send OTP to email
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $email = $request->email;
        $otpCode = (string) random_int(100000, 999999);
        
        // Delete old OTPs for this email
        Otp::where('email', $email)->delete();

        // Save new OTP
        Otp::create([
            'email' => $email,
            'otp' => $otpCode,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Send Email
        try {
            Mail::to($email)->send(new OtpMail($otpCode));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim email OTP: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP berhasil dikirim ke email'
        ]);
    }

    /**
     * Verify OTP code
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6'
        ]);

        $otpRecord = Otp::where('email', $request->email)
                        ->where('otp', $request->otp)
                        ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP salah'
            ], 400);
        }

        if (now()->greaterThan($otpRecord->expires_at)) {
            $otpRecord->delete();
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP sudah kedaluwarsa'
            ], 400);
        }

        // OTP is valid, delete it so it can't be reused
        $otpRecord->delete();

        return response()->json([
            'success' => true,
            'message' => 'Verifikasi OTP berhasil'
        ]);
    }

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
     * Register a new company and its HRD admin
     */
    public function registerCompany(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'industry' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
            'company_email' => 'nullable|email|max:255',
            'company_phone' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255',
            
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|string|min:8|confirmed',
        ]);

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            // Create company
            $company = \App\Models\Company::create([
                'name' => $validated['company_name'],
                'industry' => $validated['industry'] ?? null,
                'address' => $validated['address'] ?? null,
                'description' => $validated['description'] ?? null,
                'email' => $validated['company_email'] ?? null,
                'phone' => $validated['company_phone'] ?? null,
                'website' => $validated['website'] ?? null,
                'status' => 'active',
            ]);

            // Create HRD user
            $user = User::create([
                'company_id' => $company->id,
                'role' => 'company',
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'] ?? null,
                'is_active' => true,
            ]);

            \Illuminate\Support\Facades\DB::commit();

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Company registered successfully',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to register company: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register a new school (Step 1)
     */
    public function registerSchool(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'npsn' => 'required|string|max:50|unique:schools,npsn',
            'address' => 'required|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255|unique:schools,email',
        ]);

        $school = \App\Models\School::create([
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
     * Register a school admin (Step 2)
     */
    public function registerSchoolAdmin(Request $request)
    {
        $validated = $request->validate([
            'school_id' => 'required|exists:schools,id',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = tap(User::create([
            'school_id' => $validated['school_id'],
            'role' => 'school_admin',
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'is_active' => true,
        ]), function ($user) {
            $user->teacher()->create([
                'school_id' => $user->school_id,
                'nip' => 'ADMIN-' . time(), // Generates dummy nip placeholder
            ]);
        });

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'School admin registered successfully',
            'data' => [
                'user' => $user,
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

    /**
     * Update authenticated user's profile (name, phone, bio)
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
            'bio'   => 'sometimes|nullable|string|max:1000',
        ]);

        $user->fill($validated)->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
            'data'    => $user->fresh(),
        ]);
    }

    /**
     * Update authenticated school admin's school profile
     */
    public function updateSchoolProfile(Request $request)
    {
        $user = $request->user();
        
        if ($user->role !== 'school_admin' || !$user->school) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or invalid school'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'npsn' => 'sometimes|string|max:50',
            'address' => 'sometimes|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        $user->school->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data sekolah berhasil diperbarui',
            'data'    => $user->school->fresh(),
        ]);
    }
}

