<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\InternshipPosition;
use Illuminate\Database\Seeder;

class InternshipPositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();

        $positions = [
            [
                'title' => 'Software Developer Intern',
                'description' => 'Mencari mahasiswa/siswa untuk magang sebagai software developer. Akan belajar pengembangan aplikasi web dan mobile menggunakan teknologi terkini.',
                'quota' => 5,
            ],
            [
                'title' => 'UI/UX Designer Intern',
                'description' => 'Kesempatan magang sebagai UI/UX Designer. Akan terlibat dalam proses desain interface dan user experience untuk produk digital.',
                'quota' => 3,
            ],
            [
                'title' => 'Digital Marketing Intern',
                'description' => 'Magang di bidang digital marketing. Akan belajar strategi pemasaran digital, social media management, dan content creation.',
                'quota' => 4,
            ],
            [
                'title' => 'Data Analyst Intern',
                'description' => 'Magang sebagai data analyst. Akan belajar analisis data, visualisasi data, dan penggunaan tools analitik.',
                'quota' => 3,
            ],
            [
                'title' => 'Quality Assurance Intern',
                'description' => 'Kesempatan magang sebagai QA tester. Akan belajar testing aplikasi, bug tracking, dan quality assurance processes.',
                'quota' => 2,
            ],
        ];

        foreach ($companies as $company) {
            foreach ($positions as $index => $position) {
                $startDate = now()->addMonths($index)->startOfMonth();
                $endDate = $startDate->copy()->addMonths(6);

                InternshipPosition::create([
                    'company_id' => $company->id,
                    'title' => $position['title'],
                    'description' => $position['description'],
                    'quota' => $position['quota'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => $index % 2 === 0 ? 'open' : 'open', // All open for seeding
                ]);
            }
        }

        $this->command->info('Internship Positions seeded successfully!');
    }
}

