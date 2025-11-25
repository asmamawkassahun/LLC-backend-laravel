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
        'ssn_or_itin',
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
}
