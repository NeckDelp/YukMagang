<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internship_applications', function (Blueprint $table) {
            // CV & cover letter submitted by student
            $table->string('cv_file')->nullable()->after('position_id');
            $table->text('cover_letter')->nullable()->after('cv_file');

            // Company supervisor name (filled by HRD when approving)
            $table->string('company_supervisor_name')->nullable()->after('cover_letter');

            // Who made the school-level decision
            $table->unsignedBigInteger('school_decided_by')->nullable()->after('company_supervisor_name');
            $table->foreign('school_decided_by')->references('id')->on('users')->nullOnDelete();

            // Who made the company-level decision
            $table->unsignedBigInteger('company_decided_by')->nullable()->after('school_decided_by');
            $table->foreign('company_decided_by')->references('id')->on('users')->nullOnDelete();

            // Timestamps for decisions
            $table->timestamp('approved_school_at')->nullable()->after('company_decided_by');
            $table->timestamp('approved_company_at')->nullable()->after('approved_school_at');
            $table->timestamp('rejected_at')->nullable()->after('approved_company_at');

            // Notes
            $table->text('school_notes')->nullable()->after('rejected_at');
            $table->text('company_notes')->nullable()->after('school_notes');
        });
    }

    public function down(): void
    {
        Schema::table('internship_applications', function (Blueprint $table) {
            $table->dropForeign(['school_decided_by']);
            $table->dropForeign(['company_decided_by']);
            $table->dropColumn([
                'cv_file', 'cover_letter', 'company_supervisor_name',
                'school_decided_by', 'company_decided_by',
                'approved_school_at', 'approved_company_at', 'rejected_at',
                'school_notes', 'company_notes',
            ]);
        });
    }
};
