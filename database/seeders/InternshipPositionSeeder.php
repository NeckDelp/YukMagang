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
        InternshipPosition::create([
            'company_id' => Company::first()->id,
            'title' => 'Web Developer Intern',
            'description' => fake()->paragraph(),
            'quota' => rand(1, 5),
            'start_date' => now()->addDays(3),
            'end_date' => now()->addMonths(3),
            'status' => 'open',
        ]);
    }
}

