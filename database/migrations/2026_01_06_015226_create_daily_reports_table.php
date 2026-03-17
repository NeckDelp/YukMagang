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
       Schema::create('daily_reports', function (Blueprint $table) {
        $table->id();
        $table->foreignId('assignment_id')->constrained('internship_assignments')->cascadeOnDelete();
        $table->date('date');
        $table->text('activity');
        $table->string('file')->nullable();
        $table->enum('status', ['pending', 'approved', 'revision'])->default('pending');
        $table->timestamps();
    });

    Schema::table('daily_reports', function (Blueprint $table) {
        // Tambah field untuk export PDF
        $table->string('exported_pdf')->nullable()->after('status');
        $table->boolean('is_exported')->default(false)->after('exported_pdf');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
