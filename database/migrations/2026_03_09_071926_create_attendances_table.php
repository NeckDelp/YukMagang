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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('internship_assignments')->onDelete('cascade');
            $table->date('date');
            $table->time('clock_in_time')->nullable();
            $table->string('clock_in_ip')->nullable();
            $table->time('clock_out_time')->nullable();
            $table->string('clock_out_ip')->nullable();
            $table->enum('status', ['present', 'late', 'early_leave', 'not_clocked_out', 'absent', 'permission'])->default('absent');
            $table->text('notes')->nullable();
            $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            // Unique: 1 attendance per assignment per date
            $table->unique(['assignment_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
