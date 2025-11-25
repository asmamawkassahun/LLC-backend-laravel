<?php

namespace App\Enums;

enum CompanyStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case FORMED = 'formed';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::FORMED => 'Formed',
            self::REJECTED => 'Rejected',
        };
    }
}

