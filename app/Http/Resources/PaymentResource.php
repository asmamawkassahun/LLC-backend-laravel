<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Get order number - either from order relationship or marketplace order relationship
        $orderNumber = null;
        if ($this->order) {
            $orderNumber = $this->order->order_number;
        } elseif ($this->marketplaceOrder) {
            $orderNumber = $this->marketplaceOrder->service_order_number;
        } else {
            // Fallback: Check metadata for service_order_number (for older payments)
            $metadata = $this->metadata ?? [];
            if (isset($metadata['type']) && $metadata['type'] === 'marketplace_order') {
                $orderNumber = $metadata['service_order_number'] ?? null;
            }
        }

        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'payment_provider' => $this->payment_provider,
            'order_number' => $orderNumber,
            'transaction_id' => $this->transaction_id,
            // 'status' => $this->status?->value,
            // 'status_label' => $this->status?->label(),
            'failure_reason' => $this->failure_reason,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
