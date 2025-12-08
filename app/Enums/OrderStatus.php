<?php

namespace App\Enums;

enum OrderStatus: string
{
    // case DRAFT = 'draft';
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case PENDING = 'pending';
    case FORMED = 'formed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            // self::DRAFT => 'Draft',
            self::PENDING_PAYMENT => 'Pending Payment',
            self::PAID => 'Paid',
            self::PENDING => 'Pending',
            self::FORMED => 'Formed',
            self::CANCELLED => 'Cancelled',
        };
    }
}

