<?php

namespace Database\Seeders;

use App\Enums\PricingPlanType;
use App\Models\Country;
use App\Models\PricingPlan;
use Illuminate\Database\Seeder;

class PricingPlansSeeder extends Seeder
{
    public function run(): void
    {
        $us = Country::where('code', 'US')->first();
        $uk = Country::where('code', 'UK')->first();

        if ($us) {
            PricingPlan::updateOrCreate(
                ['slug' => 'basic-us'],
                [
                    'country_id' => $us->id,
                    'name' => 'Basic US',
                    'type' => PricingPlanType::BASIC,
                    'base_price' => 229.00,
                    'yearly_price' => 99.00,
                    'features' => [
                        'US Company Formation',
                        'US Address with Mail forwarding',
                        'Registered agent service',
                        'US business Stripe account consultation',
                        'EIN letter',
                        'Incorporation documents',
                        'Introduction to a professional accountant',
                        'Email support only',
                    ],
                    'is_active' => true,
                ]
            );

            PricingPlan::updateOrCreate(
                ['slug' => 'premium-us'],
                [
                    'country_id' => $us->id,
                    'name' => 'Premium US',
                    'type' => PricingPlanType::PREMIUM,
                    'base_price' => 397.00,
                    'yearly_price' => 99.00,
                    'features' => [
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
                    'is_active' => true,
                ]
            );
        }

        if ($uk) {
            PricingPlan::updateOrCreate(
                ['slug' => 'basic-uk'],
                [
                    'country_id' => $uk->id,
                    'name' => 'Basic UK',
                    'type' => PricingPlanType::BASIC,
                    'base_price' => 237.00,
                    'yearly_price' => 59.00,
                    'features' => [
                        'Your private company in the UK',
                        'Registered office address',
                        'UK Business Stripe account consultation',
                        'Certificate of Incorporation',
                        'Company documents',
                        'Email support only',
                    ],
                    'is_active' => true,
                ]
            );

            PricingPlan::updateOrCreate(
                ['slug' => 'premium-uk'],
                [
                    'country_id' => $uk->id,
                    'name' => 'Premium UK',
                    'type' => PricingPlanType::PREMIUM,
                    'base_price' => 337.00,
                    'yearly_price' => 59.00,
                    'features' => [
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
                    'is_active' => true,
                ]
            );
        }
    }
}
