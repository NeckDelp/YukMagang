<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\TeacherCompanySupervision;
use Illuminate\Database\Seeder;
use App\Models\Teacher;
use App\Models\Company;

class TeacherCompanySupervisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TeacherCompanySupervision::create([
            'teacher_id' => Teacher::first()->id,
            'company_id' => Company::first()->id,
        ]);
    }
}
