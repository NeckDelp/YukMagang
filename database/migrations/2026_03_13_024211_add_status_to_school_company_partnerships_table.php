<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('school_company_partnerships', function (Blueprint $table) {
            // Add status column if not exists
            if (!Schema::hasColumn('school_company_partnerships', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('company_id');
            }

            // Add partnered_at column if not exists
            if (!Schema::hasColumn('school_company_partnerships', 'partnered_at')) {
                $table->timestamp('partnered_at')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_company_partnerships', function (Blueprint $table) {
            $table->dropColumn(['status', 'partnered_at']);
        });
    }
};
