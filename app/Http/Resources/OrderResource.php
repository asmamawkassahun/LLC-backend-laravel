<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Calculate the latest updated_at from all related data
        $latestUpdatedAt = $this->calculateLatestUpdatedAt();

        // Calculate base_price from pricing plan or from subtotal
        $basePrice = null;
        if ($this->relationLoaded('pricingPlan') && $this->pricingPlan) {
            $basePrice = $this->pricingPlan->base_price;
        } else {
            // Calculate from subtotal: base_price = subtotal + discount_amount - state_fee
            $stateFee = 0;
            if ($this->relationLoaded('state') && $this->state) {
                $stateFee = $this->state->formation_fee ?? 0;
            }
            $basePrice = ($this->subtotal ?? 0) + ($this->discount_amount ?? 0) - $stateFee;
        }

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'type' => $this->type,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'payment_status' => $this->payment_status?->value,
            'payment_status_label' => $this->payment_status?->label(),
            'base_price' => $basePrice,
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
            'latest_updated_at' => $latestUpdatedAt,
        ];
    }

    /**
     * Calculate the latest updated_at from order and all related data
     */
    private function calculateLatestUpdatedAt()
    {
        $latestDate = $this->updated_at;
        $latestTimestamp = $latestDate ? $latestDate->timestamp : 0;

        // Check company's updated_at if loaded
        if ($this->relationLoaded('company') && $this->company) {
            if ($this->company->updated_at && $this->company->updated_at->timestamp > $latestTimestamp) {
                $latestDate = $this->company->updated_at;
                $latestTimestamp = $latestDate->timestamp;
            }

            // Check company owners' updated_at if loaded
            if ($this->company->relationLoaded('owners')) {
                foreach ($this->company->owners as $owner) {
                    if ($owner->updated_at && $owner->updated_at->timestamp > $latestTimestamp) {
                        $latestDate = $owner->updated_at;
                        $latestTimestamp = $latestDate->timestamp;
                    }
                }
            }

            // Check company addresses' updated_at if loaded
            if ($this->company->relationLoaded('addresses')) {
                foreach ($this->company->addresses as $address) {
                    if ($address->updated_at && $address->updated_at->timestamp > $latestTimestamp) {
                        $latestDate = $address->updated_at;
                        $latestTimestamp = $latestDate->timestamp;
                    }
                }
            }

        }

        // Check payments' updated_at if loaded
        if ($this->relationLoaded('payments')) {
            foreach ($this->payments as $payment) {
                if ($payment->updated_at && $payment->updated_at->timestamp > $latestTimestamp) {
                    $latestDate = $payment->updated_at;
                    $latestTimestamp = $latestDate->timestamp;
                }
            }
        }

        // Removed marketplace orders check - marketplace orders are now independent
        // and no longer linked to regular orders

        return $latestDate;
    }
}
