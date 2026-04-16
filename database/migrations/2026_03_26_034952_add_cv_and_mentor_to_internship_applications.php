<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internship_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('internship_applications', 'cv_file')) {
                $table->string('cv_file')->nullable()->after('position_id');
            }
            if (!Schema::hasColumn('internship_applications', 'cover_letter')) {
                $table->text('cover_letter')->nullable()->after('cv_file');
            }
            if (!Schema::hasColumn('internship_applications', 'company_supervisor_name')) {
                $table->string('company_supervisor_name')->nullable()->after('cover_letter');
            }
            if (!Schema::hasColumn('internship_applications', 'school_decided_by')) {
                $table->unsignedBigInteger('school_decided_by')->nullable()->after('company_supervisor_name');
                $table->foreign('school_decided_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('internship_applications', 'company_decided_by')) {
                $table->unsignedBigInteger('company_decided_by')->nullable()->after('school_decided_by');
                $table->foreign('company_decided_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('internship_applications', 'approved_school_at')) {
                $table->timestamp('approved_school_at')->nullable()->after('company_decided_by');
            }
            if (!Schema::hasColumn('internship_applications', 'approved_company_at')) {
                $table->timestamp('approved_company_at')->nullable()->after('approved_school_at');
            }
            if (!Schema::hasColumn('internship_applications', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_company_at');
            }
            if (!Schema::hasColumn('internship_applications', 'school_notes')) {
                $table->text('school_notes')->nullable()->after('rejected_at');
            }
            if (!Schema::hasColumn('internship_applications', 'company_notes')) {
                $table->text('company_notes')->nullable()->after('school_notes');
            }
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
