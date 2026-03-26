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
        $schools = School::all();
        $positions = [
            'Guru Mata Pelajaran',
            'Wali Kelas',
            'Guru Bimbingan Konseling',
            'Kepala Program Studi',
            'Guru Produktif',
        ];
        $expertiseMajors = [
            'Matematika',
            'Fisika',
            'Kimia',
            'Biologi',
            'Bahasa Indonesia',
            'Bahasa Inggris',
            'Pendidikan Agama',
            'Pendidikan Jasmani',
            'Teknik Informatika',
            'Pemrograman',
        ];

        // Ensure the default test teacher is seeded correctly
        $teacherUser = User::where('email', 'teacher@test.com')->first();
        $school = School::first(); // atau create dulu kalau belum ada
        
        if ($teacherUser && $school) {
            Teacher::firstOrCreate(
                ['user_id' => $teacherUser->id],
                [
                    'user_id' => $teacherUser->id,
                    'school_id' => $school->id,
                    'nip' => $school->npsn . str_pad($teacherUser->id, 6, '0', STR_PAD_LEFT),
                    'position' => $positions[0] . ' - ' . $expertiseMajors[0],
                    'expertise_majors' => [$expertiseMajors[0]],
                ]
            );
        }

        // Seed additional teachers from the users table that have a school_id
        foreach ($schools as $school) {
            $teachers = User::where('school_id', $school->id)
                ->where('role', 'teacher')
                ->get();

            foreach ($teachers as $index => $teacher) {
                Teacher::firstOrCreate(
                    ['user_id' => $teacher->id],
                    [
                        'user_id' => $teacher->id,
                        'school_id' => $school->id,
                        'nip' => $school->npsn . str_pad($teacher->id, 6, '0', STR_PAD_LEFT),
                        'position' => $positions[$index % count($positions)] . ' - ' . $expertiseMajors[$index % count($expertiseMajors)],
                        'expertise_majors' => [$expertiseMajors[$index % count($expertiseMajors)]],
                    ]
                );
            }
        }

        $this->command->info('Teachers seeded successfully!');
    }
}

