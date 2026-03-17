<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternshipPosition extends Model
{
    use HasFactory;

    protected $table = 'internship_positions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'title',
        'description',
        'requirements',      // Kolom baru
        'responsibilities',  // Kolom baru
        'benefits',          // Kolom baru
        'quota',
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
            'quota' => 'integer',
            'status' => 'string',
        ];
    }

    /**
     * Get the company that owns the internship position.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the internship applications for the position.
     */
    public function internshipApplications(): HasMany
    {
        return $this->hasMany(InternshipApplication::class, 'position_id');
    }

    /**
     * Get remaining quota
     */
    public function getRemainingQuotaAttribute()
    {
        $acceptedCount = $this->internshipApplications()
            ->where('status', 'approved_company')
            ->count();

        return $this->quota - $acceptedCount;
    }

    /**
     * Check if position is full
     */
    public function getIsFullAttribute()
    {
        return $this->remaining_quota <= 0;
    }
}
