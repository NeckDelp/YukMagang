<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternshipAssignment extends Model
{
    use HasFactory;

    protected $table = 'internship_assignments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_id',
        'school_id',
        'company_id',
        'supervisor_teacher_id',
        'start_date',
        'end_date',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => 'string',
        ];
    }

    /**
     * Get the student that owns the assignment.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the school that owns the assignment.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the company that owns the assignment.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the supervisor teacher for the assignment.
     */
    public function supervisorTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'supervisor_teacher_id');
    }

    /**
     * Get the daily reports for the assignment.
     */
    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class, 'assignment_id');
    }

    /**
     * Get the assessments for the assignment.
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'assignment_id');
    }
}
