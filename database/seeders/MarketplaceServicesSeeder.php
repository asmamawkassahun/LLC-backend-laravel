<?php

namespace Database\Seeders;

use App\Models\MarketplaceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MarketplaceServicesSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name' => 'EIN Acquisition',
                'code' => 'EIN',
                'description' => 'Get your Employer Identification Number (EIN) for your US company',
                'price' => 0.00,
            ],
            [
                'name' => 'ITIN Application',
                'code' => 'ITIN',
                'description' => 'Apply for Individual Taxpayer Identification Number',
                'price' => 0.00,
            ],
            [
                'name' => 'UK Limited Company',
                'code' => 'UKLA',
                'description' => 'UK Limited Company formation service',
                'price' => 0.00,
            ],
        ];

        foreach ($services as $service) {
            MarketplaceService::updateOrCreate(
                ['code' => $service['code']],
                array_merge($service, [
                    'slug' => Str::slug($service['name']),
                    'full_description' => null,
                    'requirements' => [],
                    'is_active' => true,
                ])
            );
        }
    }
}
