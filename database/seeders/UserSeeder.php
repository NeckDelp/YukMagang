<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schools = School::all();
        $companies = Company::all();

        // School Admin Users
        foreach ($schools as $index => $school) {
            User::firstOrCreate(
                ['email' => "admin{$school->id}@school.com"],
                [
                    'school_id' => $school->id,
                    'company_id' => null,
                    'role' => 'school_admin',
                    'name' => "Admin {$school->name}",
                    'email' => "admin{$school->id}@school.com",
                    'password' => Hash::make('password123'),
                    'phone' => '0812345678' . $school->id,
                    'is_active' => true,
                ]
            );
        }

        // Teacher Users (2 teachers per school)
        foreach ($schools as $school) {
            for ($i = 1; $i <= 2; $i++) {
                User::firstOrCreate(
                    ['email' => "teacher{$school->id}_{$i}@school.com"],
                    [
                        'school_id' => $school->id,
                        'company_id' => null,
                        'role' => 'teacher',
                        'name' => "Guru {$i} - {$school->name}",
                        'email' => "teacher{$school->id}_{$i}@school.com",
                        'password' => Hash::make('password123'),
                        'phone' => '081234567' . $school->id . $i,
                        'is_active' => true,
                    ]
                );
            }
        }

        // Student Users (5 students per school)
        foreach ($schools as $school) {
            for ($i = 1; $i <= 5; $i++) {
                User::firstOrCreate(
                    ['email' => "student{$school->id}_{$i}@school.com"],
                    [
                        'school_id' => $school->id,
                        'company_id' => null,
                        'role' => 'student',
                        'name' => "Siswa {$i} - {$school->name}",
                        'email' => "student{$school->id}_{$i}@school.com",
                        'password' => Hash::make('password123'),
                        'phone' => '08123456' . $school->id . $i,
                        'is_active' => true,
                    ]
                );
            }
        }

        // Company Users (1 user per company)
        foreach ($companies as $company) {
            User::firstOrCreate(
                ['email' => "admin{$company->id}@company.com"],
                [
                    'school_id' => null,
                    'company_id' => $company->id,
                    'role' => 'company',
                    'name' => "Admin {$company->name}",
                    'email' => "admin{$company->id}@company.com",
                    'password' => Hash::make('password123'),
                    'phone' => '081234567' . $company->id,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Users seeded successfully!');
        $this->command->info('Default password for all users: password123');
    }
}

