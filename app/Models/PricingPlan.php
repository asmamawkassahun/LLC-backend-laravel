<?php

namespace App\Models;

use App\Enums\PricingPlanType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingPlan extends Model
{
    protected $fillable = [
        'country_id',
        'name',
        'slug',
        'type',
        'base_price',
        'yearly_price',
        'features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PricingPlanType::class,
            'base_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
