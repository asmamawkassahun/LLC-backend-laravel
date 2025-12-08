<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'amount',
        'currency',
        'payment_method',
        'payment_provider',
        'transaction_id',
        'status',
        'failure_reason',
        'metadata',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => \App\Enums\PaymentStatus::class,
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function boot()
    {
        parent::boot();

        // Update order's updated_at when payment is created or updated
        static::created(function ($payment) {
            if ($payment->order_id) {
                $order = $payment->order;
                if ($order) {
                    $order->touchQuietly();
                }
            }
        });

        static::updated(function ($payment) {
            if ($payment->order_id) {
                $order = $payment->order;
                if ($order) {
                    $order->touchQuietly();
                }
            }
        });
    }
}
