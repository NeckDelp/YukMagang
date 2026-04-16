<?php

namespace App\Http\Controllers\Api\Mentor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;
use App\Models\CompanySupervisor;

class MentorPermissionController extends Controller
{
    private function getSupervisor($user)
    {
        return CompanySupervisor::where('user_id', $user->id)->first();
    }

    public function index(Request $request)
    {
        $supervisor = $this->getSupervisor($request->user());
        if (!$supervisor) return response()->json(['success' => false, 'message' => 'Not a valid mentor'], 403);

        $permissions = Permission::whereHas('student.assignments', function ($q) use ($supervisor) {
            $q->where('company_supervisor_id', $supervisor->id)->where('status', 'active');
        })
        ->with(['student.user'])
        ->orderBy('date', 'desc')
        ->paginate($request->per_page ?? 15);

        return response()->json(['success' => true, 'data' => $permissions]);
    }

    public function approve(Request $request, $id)
    {
        $supervisor = $this->getSupervisor($request->user());
        $permission = Permission::whereHas('student.assignments', function ($q) use ($supervisor) {
            $q->where('company_supervisor_id', $supervisor->id);
        })->findOrFail($id);

        $permission->update(['status' => 'approved']);
        return response()->json(['success' => true, 'message' => 'Permission approved']);
    }

    public function reject(Request $request, $id)
    {
        $supervisor = $this->getSupervisor($request->user());
        $permission = Permission::whereHas('student.assignments', function ($q) use ($supervisor) {
            $q->where('company_supervisor_id', $supervisor->id);
        })->findOrFail($id);

        $permission->update(['status' => 'rejected', 'reject_reason' => $request->reject_reason]);
        return response()->json(['success' => true, 'message' => 'Permission rejected']);
    }
}
