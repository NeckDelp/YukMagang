<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            [
                'name' => 'PT. Teknologi Indonesia',
                'industry' => 'Technology',
                'address' => 'Jl. Sudirman No. 1, Jakarta Pusat',
                'description' => 'Perusahaan teknologi terkemuka yang fokus pada pengembangan software dan solusi digital',
                'email' => 'info@teknologi.id',
                'phone' => '021-12345678',
                'website' => 'https://teknologi.id',
                'status' => 'active',
            ],
            [
                'name' => 'PT. Digital Solutions',
                'industry' => 'Information Technology',
                'address' => 'Jl. Gatot Subroto No. 88, Jakarta Selatan',
                'description' => 'Menyediakan solusi IT dan konsultasi teknologi untuk berbagai industri',
                'email' => 'contact@digitalsolutions.id',
                'phone' => '021-87654321',
                'website' => 'https://digitalsolutions.id',
                'status' => 'active',
            ],
            [
                'name' => 'PT. Bank Digital Nusantara',
                'industry' => 'Banking & Finance',
                'address' => 'Jl. Thamrin No. 100, Jakarta Pusat',
                'description' => 'Bank digital yang inovatif dengan fokus pada teknologi finansial',
                'email' => 'hr@bankdigital.id',
                'phone' => '021-55555555',
                'website' => 'https://bankdigital.id',
                'status' => 'active',
            ],
            [
                'name' => 'PT. Media Kreatif',
                'industry' => 'Media & Advertising',
                'address' => 'Jl. Kemang Raya No. 12, Jakarta Selatan',
                'description' => 'Agen kreatif yang mengkhususkan diri dalam digital marketing dan content creation',
                'email' => 'hello@mediakreatif.id',
                'phone' => '021-99999999',
                'website' => 'https://mediakreatif.id',
                'status' => 'active',
            ],
            [
                'name' => 'PT. E-Commerce Indonesia',
                'industry' => 'E-Commerce',
                'address' => 'Jl. HR Rasuna Said No. 50, Jakarta Selatan',
                'description' => 'Platform e-commerce terbesar di Indonesia',
                'email' => 'careers@ecommerce.id',
                'phone' => '021-77777777',
                'website' => 'https://ecommerce.id',
                'status' => 'active',
            ],
        ];

        foreach ($companies as $company) {
            Company::firstOrCreate(
                ['name' => $company['name']],
                $company
            );
        }

        $this->command->info('Companies seeded successfully!');
    }
}

