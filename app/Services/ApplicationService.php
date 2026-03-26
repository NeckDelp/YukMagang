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
    public function approveByCompany(InternshipApplication $application, string $companySupervisorName, ?string $companyNotes = null, ?int $companyDecidedBy = null)
    {
        return DB::transaction(function () use ($application, $companySupervisorName, $companyNotes, $companyDecidedBy) {

            if ($application->status !== ApplicationStatus::APPROVED_SCHOOL) {
                throw new \Exception('Application must be approved by school first. Current status: ' . $application->status);
            }

            // Check quota
            $acceptedCount = InternshipApplication::where('position_id', $application->position_id)
                ->where('status', ApplicationStatus::APPROVED_COMPANY)
                ->count();

            if ($acceptedCount >= $application->position->quota) {
                throw new \Exception('Quota for this position is full');
            }

            // Update application with company supervisor info
            $application->update([
                'status' => ApplicationStatus::APPROVED_COMPANY,
                'company_supervisor_name' => $companySupervisorName,
                'company_notes' => $companyNotes,
                'company_decided_by' => $companyDecidedBy,
                'approved_company_at' => now(),
            ]);

            // Create internship assignment (supervisor_teacher_id is null until school assigns one)
            InternshipAssignment::create([
                'student_id'          => $application->student_id,
                'school_id'           => $application->school_id,
                'company_id'          => $application->company_id,
                'position_id'         => $application->position_id,
                'supervisor_teacher_id' => null,
                'start_date'          => $application->position->start_date ?? now()->toDateString(),
                'end_date'            => $application->position->end_date ?? now()->addMonths(3)->toDateString(),
                'status'              => 'active',
            ]);

            return $application->fresh();
        });
    }

    /**
     * Reject by company
     */
    public function rejectByCompany(InternshipApplication $application, $user)
    {
        if (in_array($application->status, [
            ApplicationStatus::APPROVED_COMPANY,
            ApplicationStatus::REJECTED_COMPANY
        ])) {
            throw new \Exception('Application already processed');
        }

        $application->update([
            'status' => ApplicationStatus::REJECTED_COMPANY,
            'company_decided_by' => $user->id,
        ]);

        return $application->fresh();
    }

    /**
     * Approve by school (teacher)
     */
    public function approveBySchool(InternshipApplication $application, $user)
    {
        if ($application->status !== ApplicationStatus::SUBMITTED) {
            throw new \Exception('Application already processed');
        }

        $application->update([
            'status' => ApplicationStatus::APPROVED_SCHOOL,
            'school_decided_by' => $user->id,
        ]);

        return $application->fresh();
    }

    /**
     * Reject by school (teacher)
     */
    public function rejectBySchool(InternshipApplication $application, $user)
    {
        if ($application->status !== ApplicationStatus::SUBMITTED) {
            throw new \Exception('Application already processed');
        }

        $application->update([
            'status' => ApplicationStatus::REJECTED_SCHOOL,
            'school_decided_by' => $user->id,
        ]);

        return $application->fresh();
    }
}
