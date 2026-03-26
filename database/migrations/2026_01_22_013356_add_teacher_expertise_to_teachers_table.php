<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Optional: Add expertise field for better teacher-student matching
     */
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            // JSON field untuk store multiple majors/expertise
            // Example: ["Rekayasa Perangkat Lunak", "Teknik Komputer Jaringan"]
            $table->json('expertise_majors')->nullable()->after('nip');

            // Or simple text if only one major
            // $table->string('major_expertise')->nullable()->after('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('expertise_majors');
        });
    }
};
