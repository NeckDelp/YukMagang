<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assessment extends Model
{
    use HasFactory;

    protected $table = 'assessments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assignment_id',
        'assessor_role',
        'score',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'assessor_role' => 'string',
        ];
    }

    /**
     * Get the internship assignment that owns the assessment.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(InternshipAssignment::class, 'assignment_id');
    }
}
