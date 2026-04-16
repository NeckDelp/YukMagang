<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Attendance;
use App\Models\InternshipAssignment;
use Illuminate\Http\Request;

class StudentPermissionController extends Controller
{
    private function getStudentAssignment(Request $request)
    {
        $student = $request->user()->student;

        if (!$student) {
            abort(403, 'Student profile not found');
        }

        $assignment = InternshipAssignment::where('student_id', $student->id)
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            abort(403, 'No active internship assignment found');
        }

        return $assignment;
    }

    public function index(Request $request)
    {
        $assignment = $this->getStudentAssignment($request);

        $permissions = Permission::where('assignment_id', $assignment->id)
            ->orderBy('permission_date', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    public function store(Request $request)
    {
        $assignment = $this->getStudentAssignment($request);

        $validated = $request->validate([
            'permission_date' => 'required|date',
            'type' => 'required|in:sick,leave,other',
            'reason' => 'required|string',
            'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        // Check if permission already exists for this date
        $existing = Permission::where('assignment_id', $assignment->id)
            ->whereDate('permission_date', $validated['permission_date'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah mengajukan izin untuk tanggal tersebut'
            ], 422);
        }

        $filePath = null;
        if ($request->hasFile('proof_file')) {
            $filePath = $request->file('proof_file')->store('permissions', 'public');
        }

        $permission = Permission::create([
            'assignment_id' => $assignment->id,
            'permission_date' => $validated['permission_date'],
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'proof_file' => $filePath,
            'status' => 'pending'
        ]);

        // Also create/update an Attendance record as "permit"
        $attendance = Attendance::firstOrNew([
            'assignment_id' => $assignment->id,
            'date' => $validated['permission_date']
        ]);
        
        $attendance->status = 'permit';
        $attendance->notes = 'Izin diajukan: ' . $validated['type'] . ' - ' . $validated['reason'];
        $attendance->verification_status = 'pending';
        $attendance->save();

        return response()->json([
            'success' => true,
            'message' => 'Izin berhasil diajukan',
            'data' => $permission
        ], 201);
    }
}
