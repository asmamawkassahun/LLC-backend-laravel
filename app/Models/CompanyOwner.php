<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyOwner extends Model
{
    protected $fillable = [
        'company_id',
        'full_name',
        'ownership_percentage',
        'is_company',
        'email',
        'phone',
        'address',
    ];

    protected function casts(): array
    {
        return [
            'ownership_percentage' => 'decimal:2',
            'is_company' => 'boolean',
            'address' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected static function boot()
    {
        parent::boot();

        // Update order's updated_at when company owner is created or updated
        static::created(function ($owner) {
            if ($owner->company && $owner->company->order_id) {
                $order = $owner->company->order;
                if ($order) {
                    $order->touchQuietly();
                }
            }
        });

        static::updated(function ($owner) {
            if ($owner->company && $owner->company->order_id) {
                $order = $owner->company->order;
                if ($order) {
                    $order->touchQuietly();
                }
            }
        });
    }
}
