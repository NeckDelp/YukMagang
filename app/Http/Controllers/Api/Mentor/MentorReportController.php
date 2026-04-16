<?php

namespace App\Http\Controllers\Api\Mentor;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use App\Models\CompanySupervisor;
use Illuminate\Http\Request;

class MentorReportController extends Controller
{
    private function getSupervisor($user)
    {
        return CompanySupervisor::where('user_id', $user->id)->first();
    }

    public function index(Request $request)
    {
        $supervisor = $this->getSupervisor($request->user());
        if (!$supervisor) return response()->json(['success' => false, 'message' => 'Not a valid mentor'], 403);

        $reports = DailyReport::whereHas('assignment', function ($q) use ($supervisor) {
            $q->where('company_supervisor_id', $supervisor->id)
              ->where('status', 'active');
        })
        ->with(['assignment.student.user', 'assignment.position'])
        ->when($request->status, fn($q, $status) => $q->where('status', $status))
        ->orderBy('date', 'desc')
        ->paginate($request->per_page ?? 15);

        return response()->json(['success' => true, 'data' => $reports]);
    }

    public function verify(Request $request, $id)
    {
        $supervisor = $this->getSupervisor($request->user());
        if (!$supervisor) return response()->json(['success' => false, 'message' => 'Not a valid mentor'], 403);

        $report = DailyReport::whereHas('assignment', function ($q) use ($supervisor) {
            $q->where('company_supervisor_id', $supervisor->id);
        })->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:approved,revision'
        ]);

        $report->update([
            'status' => $validated['status']
        ]);

        return response()->json(['success' => true, 'message' => 'Report verified', 'data' => $report]);
    }
}
