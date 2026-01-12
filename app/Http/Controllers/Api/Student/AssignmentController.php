<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\InternshipAssignment;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    /**
     * Get student's current assignment
     */
    public function myAssignment(Request $request)
    {
        $student = $request->user()->student;

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student profile not found'
            ], 404);
        }

        // Get active assignment
        $activeAssignment = InternshipAssignment::where('student_id', $student->id)
            ->where('status', 'active')
            ->with([
                'company',
                'supervisorTeacher.user',
                'school',
                'dailyReports' => fn($q) => $q->latest()->limit(5),
                'assessments'
            ])
            ->withCount(['dailyReports', 'assessments'])
            ->first();

        // Get assignment history
        $assignmentHistory = InternshipAssignment::where('student_id', $student->id)
            ->whereIn('status', ['completed', 'cancelled'])
            ->with(['company', 'school'])
            ->latest()
            ->get();

        // Calculate statistics
        $stats = [
            'total_daily_reports' => 0,
            'approved_reports' => 0,
            'pending_reports' => 0,
            'days_completed' => 0,
            'total_days' => 0,
        ];

        if ($activeAssignment) {
            $stats['total_daily_reports'] = $activeAssignment->dailyReports()->count();
            $stats['approved_reports'] = $activeAssignment->dailyReports()
                ->where('status', 'approved')->count();
            $stats['pending_reports'] = $activeAssignment->dailyReports()
                ->where('status', 'pending')->count();

            // Calculate progress
            $startDate = $activeAssignment->start_date;
            $endDate = $activeAssignment->end_date;
            $today = now();

            if ($startDate && $endDate) {
                $stats['total_days'] = $startDate->diffInDays($endDate) + 1;
                $stats['days_completed'] = $startDate->diffInDays($today) + 1;

                if ($stats['days_completed'] > $stats['total_days']) {
                    $stats['days_completed'] = $stats['total_days'];
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'active_assignment' => $activeAssignment,
                'assignment_history' => $assignmentHistory,
                'statistics' => $stats,
            ]
        ]);
    }

    /**
     * Get assignment details by ID
     */
    public function show(Request $request, $id)
    {
        $student = $request->user()->student;

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student profile not found'
            ], 404);
        }

        $assignment = InternshipAssignment::where('student_id', $student->id)
            ->where('id', $id)
            ->with([
                'company',
                'supervisorTeacher.user',
                'school',
                'dailyReports',
                'assessments'
            ])
            ->withCount(['dailyReports', 'assessments'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $assignment
        ]);
    }
}
