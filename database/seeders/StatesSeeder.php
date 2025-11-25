<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Seeder;

class StatesSeeder extends Seeder
{
    public function run(): void
    {
        $us = Country::where('code', 'US')->first();

        if ($us) {
            $states = [
                ['name' => 'Delaware', 'code' => 'DE', 'formation_fee' => 90.00],
                ['name' => 'Wyoming', 'code' => 'WY', 'formation_fee' => 60.00],
                ['name' => 'Nevada', 'code' => 'NV', 'formation_fee' => 75.00],
                ['name' => 'Florida', 'code' => 'FL', 'formation_fee' => 125.00],
                ['name' => 'Texas', 'code' => 'TX', 'formation_fee' => 300.00],
            ];

            foreach ($states as $state) {
                State::updateOrCreate(
                    ['country_id' => $us->id, 'code' => $state['code']],
                    array_merge($state, ['is_active' => true])
                );
            }
        }
    }
}
