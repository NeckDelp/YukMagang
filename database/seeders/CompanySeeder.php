<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $company = Company::create([
            'name' => 'PT Demo Company',
        ]);

        User::where('email', 'company@test.com')->update([
            'company_id' => $company->id
        ]);
    }
}

