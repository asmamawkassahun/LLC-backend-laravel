<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CountriesSeeder::class,
            StatesSeeder::class,
            PricingPlansSeeder::class,
            MarketplaceServicesSeeder::class,
            RegisteredAgentAddressSeeder::class,
            ServicePricingSeeder::class, 
            SettingsSeeder::class,
            PromoCodeSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
