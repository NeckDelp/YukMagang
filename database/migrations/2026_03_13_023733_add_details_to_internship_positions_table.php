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
        Schema::table('internship_positions', function (Blueprint $table) {
            // Kriteria peserta magang
            $table->text('requirements')->nullable()->after('description');

            // Tugas & tanggung jawab
            $table->text('responsibilities')->nullable()->after('requirements');

            // Fasilitas & benefit
            $table->text('benefits')->nullable()->after('responsibilities');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internship_positions', function (Blueprint $table) {
            $table->dropColumn(['requirements', 'responsibilities', 'benefits']);
        });
    }
};
