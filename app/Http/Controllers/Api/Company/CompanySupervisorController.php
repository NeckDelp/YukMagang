<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\CompanySupervisor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CompanySupervisorController extends Controller
{
    private function getCompanyId(Request $request)
    {
        return $request->user()->company_id;
    }

    public function index(Request $request)
    {
        $companyId = $this->getCompanyId($request);
        $supervisors = CompanySupervisor::with('user')
            ->where('company_id', $companyId)
            ->latest()
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $supervisors
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->getCompanyId($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'company_id' => $companyId,
                'role' => 'mentor', // Peran spesifik untuk mentor perusahaan
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'],
                'is_active' => true,
            ]);

            $supervisor = CompanySupervisor::create([
                'user_id' => $user->id,
                'company_id' => $companyId,
                'name' => $validated['name'],
                'position' => $validated['position'],
                'phone' => $validated['phone'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pembimbing berhasil ditambahkan',
                'data' => $supervisor->load('user')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan pembimbing: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        $supervisor = CompanySupervisor::where('company_id', $companyId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'position' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($supervisor->user_id)],
            'password' => 'nullable|string|min:8',
        ]);

        DB::beginTransaction();
        try {
            $supervisor->update([
                'name' => $validated['name'] ?? $supervisor->name,
                'position' => $validated['position'] ?? $supervisor->position,
                'phone' => array_key_exists('phone', $validated) ? $validated['phone'] : $supervisor->phone,
            ]);

            if ($supervisor->user) {
                $userData = [
                    'name' => $validated['name'] ?? $supervisor->user->name,
                    'phone' => array_key_exists('phone', $validated) ? $validated['phone'] : $supervisor->user->phone,
                ];

                if (isset($validated['email'])) {
                    $userData['email'] = $validated['email'];
                }

                if (!empty($validated['password'])) {
                    $userData['password'] = Hash::make($validated['password']);
                }

                $supervisor->user->update($userData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pembimbing berhasil diupdate',
                'data' => $supervisor->load('user')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate pembimbing: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $this->getCompanyId($request);
        $supervisor = CompanySupervisor::where('company_id', $companyId)->findOrFail($id);

        DB::beginTransaction();
        try {
            if ($supervisor->user) {
                $supervisor->user->delete(); // This cascades depending on foreign key, but explicit is safer
            }
            $supervisor->delete();
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Pembimbing berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pembimbing: ' . $e->getMessage()
            ], 500);
        }
    }
}
