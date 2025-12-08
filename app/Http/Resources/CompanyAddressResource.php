<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // If using registered agent address, return that data
        if ($this->registered_agent_address_id && $this->registeredAgentAddress) {
            return [
                'id' => $this->id,
                'type' => $this->type,
                'use_registered_agent' => true,
                'registered_agent_address_id' => $this->registered_agent_address_id,
                'street_address' => $this->registeredAgentAddress->address,
                'city' => $this->registeredAgentAddress->city,
                'state' => $this->registeredAgentAddress->state,
                'zip_code' => $this->registeredAgentAddress->postal_code,
                'country' => $this->registeredAgentAddress->country,
                'is_active' => $this->is_active,
            ];
        }

        // Regular address
        return [
            'id' => $this->id,
            'type' => $this->type,
            'use_registered_agent' => false,
            'registered_agent_address_id' => null,
            'street_address' => $this->street_address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'country' => $this->country,
            'is_active' => $this->is_active,
        ];
    }
}
