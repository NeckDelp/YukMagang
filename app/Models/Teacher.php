<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    use HasFactory;

    protected $table = 'teachers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'school_id',
        'nip',
        'position',
    ];

    /**
     * Get the user that owns the teacher.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the school that owns the teacher.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the internship assignments supervised by the teacher.
     */
    public function supervisedAssignments(): HasMany
    {
        return $this->hasMany(InternshipAssignment::class, 'supervisor_teacher_id');
    }

    /**
     * Get supervised companies (via pivot)
     */
    public function supervisedCompanies()
    {
        return $this->belongsToMany(
            Company::class,
            'teacher_company_supervisions',
            'teacher_id',
            'company_id'
        )->withTimestamps();
    }

    /**
     * Get supervisions
     */
    public function companySupervisions()
    {
        return $this->hasMany(TeacherCompanySupervision::class);
    }
}

