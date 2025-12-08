<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyAddress extends Model
{
    protected $fillable = [
        'company_id',
        'registered_agent_address_id',
        'type',
        'street_address',
        'city',
        'state',
        'zip_code',
        'country',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function registeredAgentAddress(): BelongsTo
    {
        return $this->belongsTo(registeredAgentAddress::class, 'registered_agent_address_id');
    }

    protected static function boot()
    {
        parent::boot();

        // Update order's updated_at when company address is created or updated
        static::created(function ($address) {
            if ($address->company && $address->company->order_id) {
                $order = $address->company->order;
                if ($order) {
                    $order->touchQuietly();
                }
            }
        });

        static::updated(function ($address) {
            if ($address->company && $address->company->order_id) {
                $order = $address->company->order;
                if ($order) {
                    $order->touchQuietly();
                }
            }
        });
    }
}
