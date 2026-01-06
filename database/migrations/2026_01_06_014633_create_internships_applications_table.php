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
        Schema::create('internship_applications', function (Blueprint $table) {
        $table->id();
        $table->foreignId('student_id')->constrained()->cascadeOnDelete();
        $table->foreignId('school_id')->constrained()->cascadeOnDelete();
        $table->foreignId('company_id')->constrained()->cascadeOnDelete();
        $table->foreignId('position_id')->constrained('internship_positions')->cascadeOnDelete();
        $table->enum('status', [
            'submitted',
            'approved_school',
            'rejected_school',
            'approved_company',
            'rejected_company',
            'active',
            'finished'
        ])->default('submitted');
        $table->timestamp('applied_at')->nullable();
        $table->timestamps();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internships_applications');
    }
};
