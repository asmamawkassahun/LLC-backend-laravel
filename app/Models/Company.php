<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    protected $fillable = [
        'company_owner_ids',
        'order_id',
        'name',
        'type',
        'category',
        'country_id',
        'state_id',
        'registration_number',
        'status',
        'formed_at',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'type' => CompanyType::class,
            'status' => CompanyStatus::class,
            'formed_at' => 'datetime',
            'is_primary' => 'boolean',
            'category' => 'array',
            'company_owner_ids' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function owners(): HasMany
    {
        return $this->hasMany(CompanyOwner::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CompanyAddress::class);
    }

    public function service(): HasOne
    {
        return $this->hasOne(Service::class);
    }
}
