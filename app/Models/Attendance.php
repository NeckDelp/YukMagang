<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'date',
        'clock_in_time',
        'clock_in_ip',
        'clock_out_time',
        'clock_out_ip',
        'status',
        'notes',
        'verification_status',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'date' => 'date',
        'verified_at' => 'datetime',
        'status' => 'string',
        'verification_status' => 'string',
    ];

    /**
     * Get the assignment that owns the attendance
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(InternshipAssignment::class, 'assignment_id');
    }

    /**
     * Get the user who verified the attendance
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
