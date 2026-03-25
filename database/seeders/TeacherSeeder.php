<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teacherUser = User::where('email', 'teacher@test.com')->first();
        $school = School::first(); // atau create dulu kalau belum ada

        Teacher::create([
            'user_id' => $teacherUser->id,
            'school_id' => $school->id,
        ]);
    }
}

