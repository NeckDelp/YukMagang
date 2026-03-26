<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $studentUser = User::where('email', 'student@test.com')->first();
        $school = School::first();

        Student::create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'nis' => fake()->unique()->numerify('#####'),
            'class' => fake()->randomElement(['XII TJKT 1', 'XII TJKT 2', 'XII TJKT 3', 'XII TJKT 4']),
            'major' => 'Teknik Jaringan Komputer dan Telekomunikasi',
            'year' => now()->year,
        ]);
    }
}

