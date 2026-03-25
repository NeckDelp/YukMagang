<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SchoolSeeder::class,
            UserSeeder::class,
            CompanySeeder::class,
            TeacherSeeder::class,
            StudentSeeder::class,
            // tambahkan ini:
            SchoolCompanyPartnershipSeeder::class,
            TeacherCompanySupervisionSeeder::class,
            InternshipPositionSeeder::class,
        ]);
    }
}
