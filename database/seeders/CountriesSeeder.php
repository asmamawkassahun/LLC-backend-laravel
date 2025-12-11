<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountriesSeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'United States', 'is_active' => true],
            ['name' => 'United Kingdom', 'is_active' => true],
        ];

        foreach ($countries as $country) {
            Country::updateOrCreate(['name' => $country['name']], $country);
        }
    }
}
