<?php

// app/Http/Resources/TeacherResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nip' => $this->nip,
            'position' => $this->position,
            'expertise_majors' => $this->expertise_majors,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'photo' => $this->user->photo,
                'is_active' => $this->user->is_active,
            ],
            'school' => [
                'id' => $this->school->id,
                'name' => $this->school->name,
            ],
            'supervised_assignments_count' => $this->whenCounted('supervisedAssignments'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

// app/Http/Resources/CompanyResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'industry' => $this->industry,
            'address' => $this->address,
            'description' => $this->description,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'logo' => $this->logo ? asset('storage/' . $this->logo) : null,
            'status' => $this->status,
            'positions_count' => $this->whenCounted('internshipPositions'),
            'applications_count' => $this->whenCounted('internshipApplications'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

// app/Http/Resources/InternshipPositionResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InternshipPositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'quota' => $this->quota,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status,
            'company' => new CompanyResource($this->whenLoaded('company')),
            'applications_count' => $this->whenCounted('internshipApplications'),
            'remaining_quota' => $this->quota - ($this->internship_applications_count ?? 0),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

// app/Http/Resources/InternshipApplicationResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InternshipApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'applied_at' => $this->applied_at?->format('Y-m-d H:i:s'),
            'student' => [
                'id' => $this->student->id,
                'nis' => $this->student->nis,
                'name' => $this->student->user->name,
                'class' => $this->student->class,
                'major' => $this->student->major,
                'email' => $this->student->user->email,
                'phone' => $this->student->user->phone,
            ],
            'company' => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'industry' => $this->company->industry,
            ],
            'position' => [
                'id' => $this->position->id,
                'title' => $this->position->title,
                'quota' => $this->position->quota,
            ],
            'school' => [
                'id' => $this->school->id,
                'name' => $this->school->name,
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

// app/Http/Resources/DailyReportResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->format('Y-m-d'),
            'activity' => $this->activity,
            'file' => $this->file ? asset('storage/' . $this->file) : null,
            'status' => $this->status,
            'assignment' => [
                'id' => $this->assignment->id,
                'student' => [
                    'id' => $this->assignment->student->id,
                    'name' => $this->assignment->student->user->name,
                ],
                'company' => [
                    'id' => $this->assignment->company->id,
                    'name' => $this->assignment->company->name,
                ],
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

// app/Http/Resources/SchoolResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'npsn' => $this->npsn,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'logo' => $this->logo ? asset('storage/' . $this->logo) : null,
            'status' => $this->status,
            'users_count' => $this->whenCounted('users'),
            'students_count' => $this->whenCounted('students'),
            'teachers_count' => $this->whenCounted('teachers'),
            'assignments_count' => $this->whenCounted('internshipAssignments'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
