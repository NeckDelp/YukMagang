<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'permission_date',
        'type',
        'reason',
        'proof_file',
        'status',
        'reviewed_by',
        'review_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'permission_date' => 'date',
        'reviewed_at' => 'datetime',
        'type' => 'string',
        'status' => 'string',
    ];

    /**
     * Get the assignment
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(InternshipAssignment::class, 'assignment_id');
    }

    /**
     * Get the reviewer
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
