<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'category' => $this->category ?? [],
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'registration_number' => $this->registration_number,
            'country' => new CountryResource($this->whenLoaded('country')),
            'state' => new StateResource($this->whenLoaded('state')),
            'owners' => CompanyOwnerResource::collection($this->whenLoaded('owners')),
            'addresses' => CompanyAddressResource::collection($this->whenLoaded('addresses')),
            'service' => $this->whenLoaded('service') ? [
                'ein' => $this->service->ein,
                'itin' => $this->service->itin,
                'website' => $this->service->website,
                'domain_hosting' => $this->service->domain_hosting,
                'business_email' => $this->service->business_email,
            ] : null,
            'formed_at' => $this->formed_at,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
