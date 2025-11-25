<?php

namespace App\Enums;

enum PricingPlanType: string
{
    case BASIC = 'basic';
    case PREMIUM = 'premium';

    public function label(): string
    {
        return match($this) {
            self::BASIC => 'Basic',
            self::PREMIUM => 'Premium',
        };
    }
}

