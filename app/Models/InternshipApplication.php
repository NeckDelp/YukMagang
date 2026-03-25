<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternshipApplication extends Model
{
    use HasFactory;

    protected $table = 'internship_applications';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_id',
        'school_id',
        'company_id',
        'position_id',
        'status',
        'applied_at',
        'school_decided_by',
        'company_decided_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'status' => 'string',
        ];
    }

    /**
     * Get the student that owns the application.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the school that owns the application.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the company that owns the application.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the internship position for the application.
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(InternshipPosition::class, 'position_id');
    }

    public function schoolDecisionBy()
    {
        return $this->belongsTo(User::class, 'school_decided_by');
    }

    public function companyDecisionBy()
    {
        return $this->belongsTo(User::class, 'company_decided_by');
    }
}
