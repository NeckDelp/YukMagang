<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->time('work_start_time')->default('08:00:00')->after('status');
            $table->time('work_end_time')->default('17:00:00')->after('work_start_time');
            $table->json('working_days')->nullable()->after('work_end_time')
                ->comment('["monday", "tuesday", "wednesday", "thursday", "friday"]');
            $table->integer('late_tolerance_minutes')->default(0)->after('working_days')
                ->comment('Tolerance for late clock-in in minutes');
            $table->integer('early_leave_tolerance_minutes')->default(0)->after('late_tolerance_minutes')
                ->comment('Tolerance for early clock-out in minutes');
        });

        // Set default working days for existing companies
        DB::table('companies')->update([
            'working_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'work_start_time',
                'work_end_time',
                'working_days',
                'late_tolerance_minutes',
                'early_leave_tolerance_minutes'
            ]);
        });
    }
};
