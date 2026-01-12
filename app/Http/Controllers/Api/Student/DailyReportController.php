<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use App\Models\InternshipAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DailyReportController extends Controller
{
    /**
     * Get student's assignment for validation
     */
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

    /**
     * Display a listing of daily reports
     */
    public function index(Request $request)
    {
        $assignment = $this->getStudentAssignment($request);

        $reports = DailyReport::where('assignment_id', $assignment->id)
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->date_from, fn($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($request->date_to, fn($q, $date) => $q->whereDate('date', '<=', $date))
            ->orderBy('date', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $reports
        ]);
    }

    /**
     * Store a newly created daily report
     */
    public function store(Request $request)
    {
        $assignment = $this->getStudentAssignment($request);

        $validated = $request->validate([
            'date' => 'required|date|before_or_equal:today|after_or_equal:' . $assignment->start_date,
            'activity' => 'required|string',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120', // 5MB
        ]);

        // Check if report for this date already exists
        $existingReport = DailyReport::where('assignment_id', $assignment->id)
            ->whereDate('date', $validated['date'])
            ->exists();

        if ($existingReport) {
            return response()->json([
                'success' => false,
                'message' => 'Daily report for this date already exists'
            ], 422);
        }

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('daily-reports', 'public');
        }

        $report = DailyReport::create([
            'assignment_id' => $assignment->id,
            'date' => $validated['date'],
            'activity' => $validated['activity'],
            'file' => $filePath,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Daily report submitted successfully',
            'data' => $report
        ], 201);
    }

    /**
     * Display the specified daily report
     */
    public function show(Request $request, $id)
    {
        $assignment = $this->getStudentAssignment($request);

        $report = DailyReport::where('assignment_id', $assignment->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }

    /**
     * Update the specified daily report
     * Only allowed if status is pending or revision
     */
    public function update(Request $request, $id)
    {
        $assignment = $this->getStudentAssignment($request);

        $report = DailyReport::where('assignment_id', $assignment->id)
            ->findOrFail($id);

        if (!in_array($report->status, ['pending', 'revision'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update approved report'
            ], 422);
        }

        $validated = $request->validate([
            'activity' => 'sometimes|string',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ]);

        if ($request->hasFile('file')) {
            // Delete old file
            if ($report->file) {
                Storage::disk('public')->delete($report->file);
            }
            $validated['file'] = $request->file('file')->store('daily-reports', 'public');
        }

        $report->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Daily report updated successfully',
            'data' => $report
        ]);
    }

    /**
     * Remove the specified daily report
     * Only allowed if status is pending
     */
    public function destroy(Request $request, $id)
    {
        $assignment = $this->getStudentAssignment($request);

        $report = DailyReport::where('assignment_id', $assignment->id)
            ->findOrFail($id);

        if ($report->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete non-pending report'
            ], 422);
        }

        // Delete file if exists
        if ($report->file) {
            Storage::disk('public')->delete($report->file);
        }

        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Daily report deleted successfully'
        ]);
    }

    /**
     * Get daily report statistics
     */
    public function statistics(Request $request)
    {
        $assignment = $this->getStudentAssignment($request);

        $stats = [
            'total_reports' => DailyReport::where('assignment_id', $assignment->id)->count(),
            'approved' => DailyReport::where('assignment_id', $assignment->id)
                ->where('status', 'approved')->count(),
            'pending' => DailyReport::where('assignment_id', $assignment->id)
                ->where('status', 'pending')->count(),
            'revision' => DailyReport::where('assignment_id', $assignment->id)
                ->where('status', 'revision')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * For school/teacher to view all daily reports
     */
    public function indexForSchool(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $reports = DailyReport::whereHas('assignment', function ($query) use ($schoolId) {
            $query->where('school_id', $schoolId);
        })
            ->with(['assignment.student.user', 'assignment.company'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->assignment_id, fn($q, $id) => $q->where('assignment_id', $id))
            ->orderBy('date', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $reports
        ]);
    }

    /**
     * Approve daily report (teacher only)
     */
    public function approve(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;

        $report = DailyReport::whereHas('assignment', function ($query) use ($schoolId) {
            $query->where('school_id', $schoolId);
        })->findOrFail($id);

        $report->update(['status' => 'approved']);

        return response()->json([
            'success' => true,
            'message' => 'Daily report approved successfully',
            'data' => $report
        ]);
    }

    /**
     * Request revision for daily report (teacher only)
     */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'notes' => 'required|string'
        ]);

        $schoolId = $request->user()->school_id;

        $report = DailyReport::whereHas('assignment', function ($query) use ($schoolId) {
            $query->where('school_id', $schoolId);
        })->findOrFail($id);

        $report->update([
            'status' => 'revision',
            'notes' => $validated['notes'] ?? null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Revision requested successfully',
            'data' => $report
        ]);
    }
}
