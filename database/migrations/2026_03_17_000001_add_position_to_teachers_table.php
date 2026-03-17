<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('teachers', 'position')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->string('position')->nullable()->after('nip');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('teachers', 'position')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->dropColumn('position');
            });
        }
    }
};

