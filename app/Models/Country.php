<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = [
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function states(): HasMany
    {
        return $this->hasMany(State::class);
    }

    public function pricingPlans(): HasMany
    {
        return $this->hasMany(PricingPlan::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
