<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReport extends Model
{
    use HasFactory;

    protected $table = 'daily_reports';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assignment_id',
        'date',
        'activity',
        'file',
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
            'date' => 'date',
            'status' => 'string',
        ];
    }

    /**
     * Get the internship assignment that owns the daily report.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(InternshipAssignment::class, 'assignment_id');
    }
}
