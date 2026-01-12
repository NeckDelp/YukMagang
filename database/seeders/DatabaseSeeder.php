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
            SuperAdminSeeder::class,
            SchoolSeeder::class,
            CompanySeeder::class,
            UserSeeder::class,
            StudentSeeder::class,
            TeacherSeeder::class,
            InternshipPositionSeeder::class,
        ]);

        $this->command->info('========================================');
        $this->command->info('Database seeding completed!');
        $this->command->info('========================================');
        $this->command->info('');
        $this->command->info('Default Login Credentials:');
        $this->command->info('Super Admin: admin@lumino.com / password123');
        $this->command->info('School Admin: admin1@school.com / password123');
        $this->command->info('Teacher: teacher1_1@school.com / password123');
        $this->command->info('Student: student1_1@school.com / password123');
        $this->command->info('Company: admin1@company.com / password123');
        $this->command->info('');
        $this->command->info('Note: All users use password: password123');
    }
}
