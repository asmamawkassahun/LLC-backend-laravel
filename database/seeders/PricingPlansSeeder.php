<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\PricingPlan;
use Illuminate\Database\Seeder;

class PricingPlansSeeder extends Seeder
{
    public function run(): void
    {
        $us = Country::where('name', 'United States')->first();
        $uk = Country::where('name', 'United Kingdom')->first();

        if ($us) {
            PricingPlan::updateOrCreate(
                [
                    'country_id' => $us->id,
                    'name' => 'Basic US',
                ],
                [
                    'country_id' => $us->id,
                    'name' => 'Basic US',
                    'description' => [
                        'US Company Formation',
                        'US Address with Mail forwarding',
                        'Registered agent service',
                        'US business Stripe account consultation',
                        'EIN letter',
                        'Incorporation documents',
                        'Introduction to a professional accountant',
                        'Email support only',
                    ],
                    'base_price' => 229.00,
                    'yearly_price' => 99.00,
                    'is_active' => true,
                ]
            );

            PricingPlan::updateOrCreate(
                [
                    'country_id' => $us->id,
                    'name' => 'Premium US',
                ],
                [
                    'country_id' => $us->id,
                    'name' => 'Premium US',
                    'description' => [
                        'Everything in Basic, plus:',
                        'Order priority',
                        'FREE Tax consultation',
                        'Chat and phone support',
                        'FREE US Phone number',
                        'Dedicated account manager',
                        'FREE Business website',
                        'FREE business email inbox',
                        'FREE .com domain',
                        'Business bank consultation',
                        '3 Business logos',
                        'Bonuses',
                    ],
                    'base_price' => 397.00,
                    'yearly_price' => 99.00,
                    'is_active' => true,
                ]
            );
        }

        if ($uk) {
            PricingPlan::updateOrCreate(
                [
                    'country_id' => $uk->id,
                    'name' => 'Basic UK',
                ],
                [
                    'country_id' => $uk->id,
                    'name' => 'Basic UK',
                    'description' => [
                        'Your private company in the UK',
                        'Registered office address',
                        'UK Business Stripe account consultation',
                        'Certificate of Incorporation',
                        'Company documents',
                        'Email support only',
                    ],
                    'base_price' => 237.00,
                    'yearly_price' => 59.00,
                    'is_active' => true,
                ]
            );

            PricingPlan::updateOrCreate(
                [
                    'country_id' => $uk->id,
                    'name' => 'Premium UK',
                ],
                [
                    'country_id' => $uk->id,
                    'name' => 'Premium UK',
                    'description' => [
                        'Everything in Basic, plus:',
                        'Order priority',
                        'Chat and phone support',
                        'FREE UK Phone number',
                        'Dedicated account manager',
                        'FREE Business website',
                        'FREE business email inbox',
                        'FREE .com domain',
                        'Business bank consultation',
                        '3 Business logos',
                        'Bonuses',
                    ],
                    'base_price' => 337.00,
                    'yearly_price' => 59.00,
                    'is_active' => true,
                ]
            );
        }
    }
}
