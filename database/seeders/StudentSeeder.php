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
        $schools = School::all();
        $majors = [
            'Teknik Informatika',
            'Teknik Komputer',
            'Rekayasa Perangkat Lunak',
            'Multimedia',
            'Teknik Elektro',
            'Teknik Mesin',
            'Akuntansi',
            'Administrasi Perkantoran',
            'Pemasaran',
        ];

        $classes = ['X', 'XI', 'XII'];
        $classNumbers = ['1', '2', '3', '4', '5'];

        foreach ($schools as $school) {
            $students = User::where('school_id', $school->id)
                ->where('role', 'student')
                ->get();

            foreach ($students as $index => $student) {
                $classIndex = $index % count($classes);
                $numberIndex = ($index / count($classes)) % count($classNumbers);
                $majorIndex = $index % count($majors);

                Student::firstOrCreate(
                    ['user_id' => $student->id],
                    [
                        'user_id' => $student->id,
                        'school_id' => $school->id,
                        'nis' => $school->npsn . str_pad($student->id, 4, '0', STR_PAD_LEFT),
                        'class' => $classes[$classIndex] . ' ' . $classNumbers[$numberIndex],
                        'major' => $majors[$majorIndex],
                        'year' => 2024,
                    ]
                );
            }
        }

        $this->command->info('Students seeded successfully!');
    }
}

