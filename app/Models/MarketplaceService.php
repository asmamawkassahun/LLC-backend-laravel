<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceService extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'code',
        'description',
        'full_description',
        'requirements',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'full_description' => 'array',
            'requirements' => 'array',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function marketplaceOrders(): HasMany
    {
        return $this->hasMany(MarketplaceOrder::class);
    }
}
