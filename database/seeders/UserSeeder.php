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
        User::create([
            'name' => 'Student User',
            'email' => 'student@test.com',
            'password' => bcrypt('password'),
            'role' => 'student'
        ]);

        User::create([
            'name' => 'Teacher User',
            'email' => 'teacher@test.com',
            'password' => bcrypt('password'),
            'role' => 'teacher'
        ]);

        User::create([
            'name' => 'Company User',
            'email' => 'company@test.com',
            'password' => bcrypt('password'),
            'role' => 'company'
        ]);
    }
}

