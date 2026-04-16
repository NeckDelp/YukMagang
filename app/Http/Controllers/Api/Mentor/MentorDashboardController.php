<?php

namespace App\Http\Controllers\Api\Mentor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CompanySupervisor;
use App\Models\InternshipAssignment;
use App\Models\Task;
use App\Models\TaskSubmission;

class MentorDashboardController extends Controller
{
    private function getSupervisorId(Request $request)
    {
        $supervisor = CompanySupervisor::where('user_id', '=', $request->user()->id)->first();
        return $supervisor ? $supervisor->id : null;
    }

    public function index(Request $request)
    {
        $supervisorId = $this->getSupervisorId($request);
        
        if (!$supervisorId) {
            return response()->json(['success' => false, 'message' => 'Profil pembimbing tidak ditemukan'], 404);
        }

        $activeInterns = InternshipAssignment::where('company_supervisor_id', '=', $supervisorId)
            ->where('status', '=', 'active')
            ->count();
            
        $completedInterns = InternshipAssignment::where('company_supervisor_id', '=', $supervisorId)
            ->where('status', '=', 'completed')
            ->count();

        $userId = $request->user()->id;

        $totalTasks = Task::where('created_by', '=', $userId)->count();

        $pendingSubmissions = TaskSubmission::whereHas('task', function($q) use ($userId) {
            $q->where('created_by', '=', $userId);
        })->where('status', '=', 'submitted')->count();

        $recentStudents = InternshipAssignment::where('company_supervisor_id', $supervisorId)
            ->where('status', 'active')
            ->with(['student.user'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->student_id,
                    'name' => $assignment->student->user->name ?? 'Unknown',
                    'nis' => $assignment->student->nis ?? '-',
                    'position' => $assignment->position?->title ?? 'Siswa PKL',
                    'school' => $assignment->school->name ?? '-',
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'active_interns' => $activeInterns,
                'completed_interns' => $completedInterns,
                'total_tasks' => $totalTasks,
                'pending_submissions' => $pendingSubmissions,
                'recent_students' => $recentStudents
            ]
        ]);
    }
}
