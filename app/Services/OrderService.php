<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PricingPlan;
use App\Models\PromoCode;
use App\Models\State;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function createOrder(array $data, $user): Order
    {
        return DB::transaction(function () use ($data, $user) {
            $orderNumber = $this->generateOrderNumber();
            
            $pricingPlan = PricingPlan::findOrFail($data['pricing_plan_id']);
            $stateFee = 0;
            
            if (isset($data['state_id'])) {
                $state = State::findOrFail($data['state_id']);
                $stateFee = $state->formation_fee;
            }
            
            $basePrice = $pricingPlan->base_price;
            $subtotal = $basePrice + $stateFee;
            $discountAmount = 0;
            
            if (isset($data['promo_code'])) {
                $promoCode = PromoCode::where('code', $data['promo_code'])->first();
                if ($promoCode && $this->isPromoCodeValid($promoCode, $subtotal)) {
                    $discountAmount = $this->calculateDiscount($promoCode, $subtotal);
                    $subtotal -= $discountAmount;
                }
            }
            
            $taxAmount = 0; // Calculate tax if needed
            $totalAmount = $subtotal + $taxAmount;
            
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => $orderNumber,
                'type' => $data['type'],
                'country_id' => $data['country_id'] ?? null,
                'pricing_plan_id' => $data['pricing_plan_id'] ?? null,
                'state_id' => $data['state_id'] ?? null,
                'state_fee' => $stateFee,
                'base_price' => $basePrice,
                'discount_amount' => $discountAmount,
                'promo_code_id' => $promoCode->id ?? null,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => OrderStatus::DRAFT,
                'metadata' => $data['metadata'] ?? [],
            ]);
            
            return $order;
        });
    }
    
    public function calculateTotal(Order $order): array
    {
        $subtotal = $order->base_price + $order->state_fee - $order->discount_amount;
        $taxAmount = $order->tax_amount;
        $totalAmount = $subtotal + $taxAmount;
        
        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ];
    }
    
    public function applyPromoCode(Order $order, string $code): array
    {
        $promoCode = PromoCode::where('code', $code)->first();
        
        if (!$promoCode) {
            throw new \Exception('Promo code not found');
        }
        
        if (!$this->isPromoCodeValid($promoCode, $order->subtotal)) {
            throw new \Exception('Promo code is not valid');
        }
        
        $discountAmount = $this->calculateDiscount($promoCode, $order->subtotal);
        
        $order->update([
            'promo_code_id' => $promoCode->id,
            'discount_amount' => $discountAmount,
        ]);
        
        $totals = $this->calculateTotal($order);
        $order->update($totals);
        
        $promoCode->increment('used_count');
        
        return $totals;
    }
    
    public function updateOrderStatus(Order $order, OrderStatus $status): void
    {
        $order->update(['status' => $status]);
        
        if ($status === OrderStatus::COMPLETED) {
            $order->update(['completed_at' => now()]);
        } elseif ($status === OrderStatus::CANCELLED) {
            $order->update(['cancelled_at' => now()]);
        }
    }
    
    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-' . strtoupper(Str::random(10));
        } while (Order::where('order_number', $orderNumber)->exists());
        
        return $orderNumber;
    }
    
    private function isPromoCodeValid(PromoCode $promoCode, float $subtotal): bool
    {
        if (!$promoCode->is_active) {
            return false;
        }
        
        if (now()->lt($promoCode->valid_from) || now()->gt($promoCode->valid_until)) {
            return false;
        }
        
        if ($promoCode->usage_limit && $promoCode->used_count >= $promoCode->usage_limit) {
            return false;
        }
        
        if ($promoCode->min_purchase_amount && $subtotal < $promoCode->min_purchase_amount) {
            return false;
        }
        
        return true;
    }
    
    private function calculateDiscount(PromoCode $promoCode, float $subtotal): float
    {
        if ($promoCode->type === 'percentage') {
            $discount = ($subtotal * $promoCode->value) / 100;
        } else {
            $discount = $promoCode->value;
        }
        
        if ($promoCode->max_discount_amount) {
            $discount = min($discount, $promoCode->max_discount_amount);
        }
        
        return round($discount, 2);
    }
}

