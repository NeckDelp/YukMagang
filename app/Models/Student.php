<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Student extends Model
{
    use HasFactory;

    protected $table = 'students';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'school_id',
        'nis',
        'class',
        'major',
        'year',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
        ];
    }

    /**
     * Get the user that owns the student.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the school that owns the student.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the internship applications for the student.
     */
    public function internshipApplications(): HasMany
    {
        return $this->hasMany(InternshipApplication::class);
    }

    /**
     * Get the internship assignments for the student.
     */
    public function internshipAssignments(): HasMany
    {
        return $this->hasMany(InternshipAssignment::class);
    }

    /**
     * Get the permissions for the student.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'assignment_id', 'id');
    }

    /**
     * Get the active internship assignment
     */
    public function activeAssignment()
    {
        return $this->hasOne(InternshipAssignment::class)
            ->where('status', 'active')
            ->with(['company', 'position']);
    }

    /**
     * Helper: Check if student has active internship
     */
    public function isActive()
    {
        return $this->internshipAssignments()
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Helper: Get attendance statistics
     */
    public function getAttendanceStats($month = null, $year = null)
    {
        $assignment = $this->activeAssignment;

        if (!$assignment) {
            return null;
        }

        $month = $month ?? Carbon::now()->month;
        $year = $year ?? Carbon::now()->year;

        return [
            'total_days' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->count(),
            'present' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->where('status', 'present')->count(),
            'late' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->where('status', 'late')->count(),
            'permission' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->where('status', 'permission')->count(),
            'absent' => Attendance::where('assignment_id', $assignment->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->where('status', 'absent')->count(),
        ];
    }

    /**
     * Helper: Get task statistics
     */
    public function getTaskStats()
    {
        $taskRecipientIds = DB::table('task_recipients')
            ->where('student_id', $this->id)
            ->pluck('task_id');

        return [
            'total' => $taskRecipientIds->count(),
            'completed' => TaskSubmission::whereIn('task_id', $taskRecipientIds)
                ->where('student_id', $this->id)
                ->where('status', 'approved')
                ->count(),
            'pending' => TaskSubmission::whereIn('task_id', $taskRecipientIds)
                ->where('student_id', $this->id)
                ->whereIn('status', ['submitted', 'in_progress'])
                ->count(),
        ];
    }
}
