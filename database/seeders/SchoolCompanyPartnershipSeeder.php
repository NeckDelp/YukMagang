<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\SchoolCompanyPartnership;
use Illuminate\Database\Seeder;
use App\Models\School;
use App\Models\Company;

class SchoolCompanyPartnershipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SchoolCompanyPartnership::create([
            'school_id' => School::first()->id,
            'company_id' => Company::first()->id,
            'status' => 'active',
        ]);
    }
}
