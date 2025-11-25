<?php

namespace App\Enums;

enum CompanyType: string
{
    case LLC = 'LLC';
    case LTD = 'LTD';
    case CORP = 'CORP';

    public function label(): string
    {
        return match($this) {
            self::LLC => 'Limited Liability Company',
            self::LTD => 'Limited Company',
            self::CORP => 'Corporation',
        };
    }
}

