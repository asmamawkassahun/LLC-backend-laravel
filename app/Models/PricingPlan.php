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
        'description',
        'base_price',
        'yearly_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'description' => 'array',
            'base_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
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
