<?php

namespace Database\Seeders;

use App\Models\PromoCode;
use Illuminate\Database\Seeder;

class PromoCodeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        
        // Percentage-based promo codes
        PromoCode::updateOrCreate(
            ['code' => 'WELCOME10'],
            [
                'type' => 'percentage',
                'value' => 10.00,
                'min_purchase_amount' => 50.00,
                'max_discount_amount' => 50.00,
                'usage_limit' => 100,
                'used_count' => 0,
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
                'is_active' => true,
            ]
        );

        PromoCode::updateOrCreate(
            ['code' => 'SAVE20'],
            [
                'type' => 'percentage',
                'value' => 20.00,
                'min_purchase_amount' => 100.00,
                'max_discount_amount' => 100.00,
                'usage_limit' => 50,
                'used_count' => 0,
                'valid_from' => $now,
                'valid_until' => $now->copy()->addMonths(6),
                'is_active' => true,
            ]
        );

        PromoCode::updateOrCreate(
            ['code' => 'HOLIDAY25'],
            [
                'type' => 'percentage',
                'value' => 25.00,
                'min_purchase_amount' => 200.00,
                'max_discount_amount' => 150.00,
                'usage_limit' => 25,
                'used_count' => 0,
                'valid_from' => $now,
                'valid_until' => $now->copy()->addMonths(3),
                'is_active' => true,
            ]
        );

        // Fixed amount promo codes
        PromoCode::updateOrCreate(
            ['code' => 'FLAT50'],
            [
                'type' => 'fixed',
                'value' => 50.00,
                'min_purchase_amount' => 150.00,
                'max_discount_amount' => null,
                'usage_limit' => 75,
                'used_count' => 0,
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYear(),
                'is_active' => true,
            ]
        );

        PromoCode::updateOrCreate(
            ['code' => 'FLAT100'],
            [
                'type' => 'fixed',
                'value' => 100.00,
                'min_purchase_amount' => 300.00,
                'max_discount_amount' => null,
                'usage_limit' => 30,
                'used_count' => 0,
                'valid_from' => $now,
                'valid_until' => $now->copy()->addMonths(6),
                'is_active' => true,
            ]
        );

        // Admin-specific promo code (unlimited usage, high discount)
        PromoCode::updateOrCreate(
            ['code' => 'ADMIN50'],
            [
                'type' => 'percentage',
                'value' => 50.00,
                'min_purchase_amount' => null,
                'max_discount_amount' => null,
                'usage_limit' => null, // Unlimited
                'used_count' => 0,
                'valid_from' => $now,
                'valid_until' => $now->copy()->addYears(5),
                'is_active' => true,
            ]
        );

        // Inactive promo code example
        PromoCode::updateOrCreate(
            ['code' => 'EXPIRED'],
            [
                'type' => 'percentage',
                'value' => 15.00,
                'min_purchase_amount' => 75.00,
                'max_discount_amount' => 75.00,
                'usage_limit' => 10,
                'used_count' => 10,
                'valid_from' => $now->copy()->subMonths(6),
                'valid_until' => $now->copy()->subMonth(),
                'is_active' => false,
            ]
        );
    }
}

