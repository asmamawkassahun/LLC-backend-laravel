<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicePricing extends Model
{
    protected $table = 'service_pricing';

    protected $fillable = [
        'name',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }
}
