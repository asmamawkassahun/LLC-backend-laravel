<?php

namespace Database\Seeders;

use App\Models\ServicePricing;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServicePricingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => 'ein',
                'price' => 349.00,
            ],
            [
                'name' => 'itin',
                'price' => 299.00,
            ],
            [
                'name' => 'website',
                'price' => 999.00,
            ],
            [
                'name' => 'domain_hosting',
                'price' => 499.00,
            ],
            [
                'name' => 'business_email',
                'price' => 297.00,
            ],
        ];

        foreach ($services as $service) {
            ServicePricing::updateOrCreate(
                ['name' => $service['name']],
                $service
            );
        }
    }
}
