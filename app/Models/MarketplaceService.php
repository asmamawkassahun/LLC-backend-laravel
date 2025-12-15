<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceService extends Model
{
    protected $fillable = [
        'name',
        'description',
        'requirements',
        'price',
        'is_active',
        'country_id',
    ];

    protected function casts(): array
    {
        return [
            'requirements' => 'array',
            'country_id' => 'array',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function marketplaceOrders(): HasMany
    {
        return $this->hasMany(MarketplaceOrder::class);
    }

    public function countries()
    {
        return Country::whereIn('id', $this->country_id ?? [])->get();
    }
}
