<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Company extends Model
{
    use HasFactory;

    protected $table = 'companies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'industry',
        'address',
        'city',
        'province',
        'description',
        'email',
        'phone',
        'website',
        'logo',
        'status',
        'work_start_time',
        'work_end_time',
        'working_days',
        'late_tolerance_minutes',
        'early_leave_tolerance_minutes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'string',
            'working_days' => 'array',
            'work_start_time' => 'datetime:H:i:s',
            'work_end_time' => 'datetime:H:i:s',
            'late_tolerance_minutes' => 'integer',
            'early_leave_tolerance_minutes' => 'integer',
        ];
    }

    /**
     * Get the users (company admins) for the company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the internship positions for the company.
     */
    public function internshipPositions(): HasMany
    {
        return $this->hasMany(InternshipPosition::class);
    }

    /**
     * Get the internship applications for the company.
     */
    public function internshipApplications(): HasMany
    {
        return $this->hasMany(InternshipApplication::class);
    }

    /**
     * Get the internship assignments for the company.
     */
    public function internshipAssignments(): HasMany
    {
        return $this->hasMany(InternshipAssignment::class);
    }

    /**
     * Get partnered schools (via pivot)
     */
    public function partneredSchools()
    {
        return $this->belongsToMany(
            School::class,
            'school_company_partnerships',
            'company_id',
            'school_id'
        )
        ->withPivot('status', 'partnered_at')
        ->withTimestamps();
    }

    /**
     * Get supervising teachers
     */
    public function supervisingTeachers()
    {
        return $this->belongsToMany(
            Teacher::class,
            'teacher_company_supervisions',
            'company_id',
            'teacher_id'
        )->withTimestamps();
    }

    /**
     * Helper: Check if a date is a working day
     */
    public function isWorkingDay($date)
    {
        $dayName = strtolower($date->format('l')); // monday, tuesday, etc
        $workingDays = $this->working_days ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

        return in_array($dayName, $workingDays);
    }

    /**
     * Helper: Check if clock in time is late
     */
    public function isLate($clockInTime)
    {
        $workStart = Carbon::parse($this->work_start_time);
        $clockIn = Carbon::parse($clockInTime);
        $tolerance = $this->late_tolerance_minutes ?? 0;

        return $clockIn->greaterThan($workStart->addMinutes($tolerance));
    }

    /**
     * Helper: Check if clock out time is early
     */
    public function isEarlyLeave($clockOutTime)
    {
        $workEnd = Carbon::parse($this->work_end_time);
        $clockOut = Carbon::parse($clockOutTime);
        $tolerance = $this->early_leave_tolerance_minutes ?? 0;

        return $clockOut->lessThan($workEnd->subMinutes($tolerance));
    }
}
