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
            'Teknik Komputer dan Jaringan',
            'Teknik Instalasi Tenaga Listrik',
            'Teknik Otomasi Industri',
            'Desain Komunikasi Visual',
            'Broadcasting dan Perfilman',
            'Teknik Otomotif',
            'Teknik Mesin',
            'Teknik Pengelasan dan Fabrikasi Logam',
            'Kimia Analisis',
            'Akuntansi dan Keuangan Lembaga',
            'Keperawatan',
            'Rekayasa Perangkat Lunak',
            'Teknik Kendaraan Ringan',
            'Pemasaran',
            'Usaha Layanan Pariwisata',
            'Manager Perkantoran dan Layanan Bisnis',
            'Desain Pemodelan dan Informasi Bangunan',
            'Teknik Audio Video',
            'Teknik Sepeda Motor',
            'Teknik Alat Berat',
            'Perhotelan',
            'Kuliner',
            'Tata Busana',
            'Tata Kecantikan Kulit dan Rambut',
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

