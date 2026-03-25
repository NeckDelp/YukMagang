<?php

namespace App\Services;

use App\Models\InternshipApplication;
use App\Models\InternshipAssignment;
use App\Enums\ApplicationStatus;
use Illuminate\Support\Facades\DB;

class ApplicationService
{
    /**
     * Approve by company (FINAL STEP)
     */
    public function approveByCompany(InternshipApplication $application)
    {
        return DB::transaction(function () use ($application) {

            if ($application->status !== ApplicationStatus::APPROVED_SCHOOL) {
                throw new \Exception('Application must be approved by school first');
            }

            // Check quota
            $acceptedCount = InternshipApplication::where('position_id', $application->position_id)
                ->where('status', ApplicationStatus::APPROVED_COMPANY)
                ->count();

            if ($acceptedCount >= $application->position->quota) {
                throw new \Exception('Quota full');
            }

            // Update status
            $application->update([
                'status' => ApplicationStatus::APPROVED_COMPANY,
            ]);

            // CREATE ASSIGNMENT
            InternshipAssignment::create([
                'student_id' => $application->student_id,
                'school_id' => $application->school_id,
                'company_id' => $application->company_id,
                'supervisor_teacher_id' => null, // atau isi kalau ada
                'start_date' => $application->position->start_date,
                'end_date' => $application->position->end_date,
                'status' => 'active',
            ]);

            return $application;

            $exists = InternshipAssignment::where('student_id', $application->student_id)
                ->where('status', 'active')
                ->exists();

            if ($exists) {
                throw new \Exception('Student already has active internship');
            }
        });
    }

    /**
     * Approve by school (teacher)
     */
    public function approveBySchool(InternshipApplication $application, $userId, $notes = null)
    {
        if ($application->status !== ApplicationStatus::SUBMITTED) {
            throw new \Exception('Application already processed');
        }

        $application->update([
            'status' => ApplicationStatus::APPROVED_SCHOOL,
            'approved_by_school' => $userId,
            'approved_school_at' => now(),
            'school_notes' => $notes ?? 'Approved by school',
        ]);

        return $application;
    }
}
