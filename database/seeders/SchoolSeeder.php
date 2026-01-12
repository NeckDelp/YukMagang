<?php

namespace Database\Seeders;

use App\Models\School;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schools = [
            [
                'name' => 'SMA Negeri 1 Jakarta',
                'npsn' => '20100101',
                'address' => 'Jl. Budi Utomo No. 7, Jakarta Pusat',
                'phone' => '021-3844294',
                'email' => 'sman1@jakarta.sch.id',
                'status' => 'active',
            ],
            [
                'name' => 'SMA Negeri 2 Bandung',
                'npsn' => '20200101',
                'address' => 'Jl. Cihampelas No. 173, Bandung',
                'phone' => '022-2031095',
                'email' => 'sman2@bandung.sch.id',
                'status' => 'active',
            ],
            [
                'name' => 'SMA Negeri 3 Surabaya',
                'npsn' => '20300101',
                'address' => 'Jl. Mayjen Sungkono No. 1, Surabaya',
                'phone' => '031-5345978',
                'email' => 'sman3@surabaya.sch.id',
                'status' => 'active',
            ],
            [
                'name' => 'SMK Negeri 1 Jakarta',
                'npsn' => '30100101',
                'address' => 'Jl. Budi Utomo No. 1, Jakarta Pusat',
                'phone' => '021-3844295',
                'email' => 'smkn1@jakarta.sch.id',
                'status' => 'active',
            ],
            [
                'name' => 'SMK Negeri 2 Yogyakarta',
                'npsn' => '30200101',
                'address' => 'Jl. AM Sangaji No. 47, Yogyakarta',
                'phone' => '0274-512929',
                'email' => 'smkn2@yogyakarta.sch.id',
                'status' => 'active',
            ],
        ];

        foreach ($schools as $school) {
            School::firstOrCreate(
                ['npsn' => $school['npsn']],
                $school
            );
        }

        $this->command->info('Schools seeded successfully!');
    }
}

