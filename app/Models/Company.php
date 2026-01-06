<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'description',
        'email',
        'phone',
        'website',
        'logo',
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
            'status' => 'string',
        ];
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
}
