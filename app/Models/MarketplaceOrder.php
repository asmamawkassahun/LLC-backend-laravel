<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceOrder extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'marketplace_service_id',
        'promo_code_id',
        'company_id',
        'status',
        'requirements_met',
        'delivered_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'requirements_met' => 'boolean',
            'delivered_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function marketplaceService(): BelongsTo
    {
        return $this->belongsTo(MarketplaceService::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected static function boot()
    {
        parent::boot();

        // Update order's updated_at when marketplace order is created or updated
        static::created(function ($marketplaceOrder) {
            if ($marketplaceOrder->order_id) {
                $order = $marketplaceOrder->order;
                if ($order) {
                    $order->touchQuietly();
                }
            }
        });

        static::updated(function ($marketplaceOrder) {
            if ($marketplaceOrder->order_id) {
                $order = $marketplaceOrder->order;
                if ($order) {
                    $order->touchQuietly();
                }
            }
        });
    }
}
