<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceOrder extends Model
{
    protected $fillable = [
        'user_id',
        'service_order_number',
        'marketplace_service_id',
        'amount',
        'company_id',
        'status',
        'requirements_met',
        'delivered_at',
        'metadata',
        'file',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requirements_met' => 'boolean',
            'delivered_at' => 'datetime',
            'metadata' => 'array',
            'file' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function marketplaceService(): BelongsTo
    {
        return $this->belongsTo(MarketplaceService::class);
    }


    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected static function boot()
    {
        parent::boot();
        // Removed order touching logic since we no longer have order_id relationship
    }
}
