<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InternshipAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status,
            'student' => [
                'id' => $this->student->id,
                'nis' => $this->student->nis,
                'name' => $this->student->user->name,
                'class' => $this->student->class,
                'major' => $this->student->major,
            ],
            'company' => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'industry' => $this->company->industry,
                'address' => $this->company->address,
            ],
            'supervisor' => [
                'id' => $this->supervisorTeacher->id,
                'nip' => $this->supervisorTeacher->nip,
                'name' => $this->supervisorTeacher->user->name,
                'position' => $this->supervisorTeacher->position,
                'expertise_majors' => $this->supervisorTeacher->expertise_majors,
            ],
            'school' => [
                'id' => $this->school->id,
                'name' => $this->school->name,
            ],
            'daily_reports_count' => $this->whenCounted('dailyReports'),
            'assessments_count' => $this->whenCounted('assessments'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
