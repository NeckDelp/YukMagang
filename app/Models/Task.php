<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'school_id',
        'title',
        'description',
        'expected_output',
        'deadline',
        'status',
        'attachment_file',
    ];

    protected $casts = [
        'deadline' => 'date',
        'status' => 'string',
    ];

    /**
     * Get the teacher who created the task
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'created_by');
    }

    /**
     * Get the school
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the recipients
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(TaskRecipient::class);
    }

    /**
     * Get the submissions
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(TaskSubmission::class);
    }
}
