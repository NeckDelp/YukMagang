<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolCompanyPartnership extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'company_id',
        'status',
        'partnered_at',
    ];

    protected $casts = [
        'partnered_at' => 'datetime',
        'status' => 'string',
    ];

    /**
     * Get the school
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the company
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
