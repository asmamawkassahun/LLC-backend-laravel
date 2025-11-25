<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyOwnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'ownership_percentage' => $this->ownership_percentage,
            'is_company' => $this->is_company,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
}
