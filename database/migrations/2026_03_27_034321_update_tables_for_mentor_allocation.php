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
        Schema::table('internship_assignments', function (Blueprint $table) {
            $table->foreignId('company_supervisor_id')->nullable()->after('company_id')
                  ->constrained('company_supervisors')->nullOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreign('created_by')->references('id')->on('teachers')->cascadeOnDelete();
        });

        Schema::table('internship_assignments', function (Blueprint $table) {
            $table->dropForeign(['company_supervisor_id']);
            $table->dropColumn('company_supervisor_id');
        });
    }
};
