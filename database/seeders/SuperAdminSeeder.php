<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@lumino.com'],
            [
                'school_id' => null,
                'company_id' => null,
                'role' => 'super_admin',
                'name' => 'Super Administrator',
                'email' => 'admin@lumino.com',
                'password' => Hash::make('password123'),
                'phone' => '081234567890',
                'is_active' => true,
            ]
        );

        $this->command->info('Super Admin created successfully!');
        $this->command->info('Email: admin@lumino.com');
        $this->command->info('Password: password123');
    }
}

