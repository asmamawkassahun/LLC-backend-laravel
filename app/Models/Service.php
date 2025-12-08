<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    protected $fillable = [
        'company_id',
        'ein',
        'itin',
        'website',
        'domain_hosting',
        'business_email',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected static function boot()
    {
        parent::boot();

        // Update order's updated_at when service is updated
        static::updated(function ($service) {
            if ($service->company && $service->company->order_id) {
                $order = $service->company->order;
                if ($order) {
                    $order->touchQuietly();
                }
            }
        });
    }
}
