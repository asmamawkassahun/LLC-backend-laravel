<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'type' => $this->type,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'payment_status' => $this->payment_status?->value,
            'payment_status_label' => $this->payment_status?->label(),
            'base_price' => $this->base_price,
            'discount_amount' => $this->discount_amount,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'country' => new CountryResource($this->whenLoaded('country')),
            'pricing_plan' => new PricingPlanResource($this->whenLoaded('pricingPlan')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'state' => new StateResource($this->whenLoaded('state')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'paid_at' => $this->paid_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
