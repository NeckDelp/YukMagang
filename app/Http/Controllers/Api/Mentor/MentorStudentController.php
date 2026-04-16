<?php

namespace App\Http\Controllers\Api\Mentor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CompanySupervisor;
use App\Models\InternshipAssignment;

class MentorStudentController extends Controller
{
    private function getSupervisorId(Request $request)
    {
        $supervisor = CompanySupervisor::where('user_id', $request->user()->id)->first();
        return $supervisor ? $supervisor->id : null;
    }

    public function index(Request $request)
    {
        $supervisorId = $this->getSupervisorId($request);
        
        if (!$supervisorId) {
            return response()->json(['success' => false, 'message' => 'Profil pembimbing tidak ditemukan'], 404);
        }

        $interns = InternshipAssignment::where('company_supervisor_id', $supervisorId)
            ->with(['student.user', 'student.school', 'position'])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $interns
        ]);
    }

    public function show(Request $request, $id)
    {
        $supervisorId = $this->getSupervisorId($request);
        
        if (!$supervisorId) {
            return response()->json(['success' => false, 'message' => 'Profil pembimbing tidak ditemukan'], 404);
        }

        $assignment = InternshipAssignment::where('company_supervisor_id', $supervisorId)
            ->where('id', $id)
            ->with([
                'student.user', 
                'student.school', 
                'position', 
                'company',
                'supervisorTeacher.user'
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $assignment
        ]);
    }
}
