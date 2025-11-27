<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CompanyOwner;
use App\Models\Country;
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
            // Step 1: Country Storage
            $countryName = $data['plan'][0]['countryName'] ?? null;
            if (!$countryName) {
                throw new \Exception('Country name is required');
            }
            
            $country = Country::where('name', $countryName)->first();
            if (!$country) {
                $country = Country::create([
                    'name' => $countryName,
                    'is_active' => true,
                ]);
            }
            $countryId = $country->id;
            
            // Step 2: State Storage
            $stateId = null;
            $state = null;
            if (isset($data['state']['name']) && !empty($data['state']['name'])) {
                $stateName = $data['state']['name'];
                $stateCost = $data['state']['cost'] ?? 0;
                
                $state = State::where('country_id', $countryId)
                    ->where('name', $stateName)
                    ->first();
                
                if (!$state) {
                    $state = State::create([
                        'country_id' => $countryId,
                        'name' => $stateName,
                        'formation_fee' => $stateCost,
                        'code' => null,
                        'is_active' => null,
                    ]);
                }
                $stateId = $state->id;
            }
            
            // Step 3: Pricing Plan Storage
            $pricingPlanName = $data['plan'][0]['pricingPlan'] ?? null;
            $basePrice = $data['plan'][0]['basePrice'] ?? 0;
            $yearlyPrice = $data['plan'][0]['yearlyPrice'] ?? 0;
            
            if (!$pricingPlanName) {
                throw new \Exception('Pricing plan name is required');
            }
            
            $pricingPlan = PricingPlan::where('country_id', $countryId)
                ->where('name', $pricingPlanName)
                ->first();
            
            if (!$pricingPlan) {
                $pricingPlan = PricingPlan::create([
                    'country_id' => $countryId,
                    'name' => $pricingPlanName,
                    'base_price' => $basePrice,
                    'yearly_price' => $yearlyPrice,
                    'slug' => null,
                    'features' => null,
                    'type' => null,
                    'is_active' => true,
                ]);
            }
            $pricingPlanId = $pricingPlan->id;
            
            // Step 4: Company Storage
            $company = Company::create([
                'user_id' => $user->id,
                'order_id' => null, // Will be updated after order creation
                'name' => $data['company_name'],
                'type' => $data['company_type'],
                'country_id' => $countryId,
                'state_id' => $stateId,
                'status' => CompanyStatus::PENDING,
            ]);
            $companyId = $company->id;
            
            // Step 5: Order Storage
            $orderNumber = $this->generateOrderNumber();
            
            // Calculate totals from relationships
            $basePriceValue = $pricingPlan->base_price;
            $stateFee = $state ? ($state->formation_fee ?? 0) : 0;
            $subtotal = $basePriceValue + $stateFee;
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
                'type' => $data['type'] ?? 'company_formation',
                'country_id' => $countryId,
                'pricing_plan_id' => $pricingPlanId,
                'state_id' => $stateId,
                'company_id' => $companyId,
                'discount_amount' => $discountAmount,
                'promo_code_id' => $promoCode->id ?? null,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => OrderStatus::DRAFT,
                'payment_status' => PaymentStatus::UNPAID,
                'metadata' => $data['metadata'] ?? [],
            ]);
            
            // Step 6: Update company with order_id
            $company->update(['order_id' => $order->id]);
            
            // Step 7: Company Address and Owner Storage
            if (isset($data['owners']) && is_array($data['owners'])) {
                foreach ($data['owners'] as $ownerData) {
                    CompanyOwner::create([
                        'company_id' => $companyId,
                        'full_name' => $ownerData['full_name'] ?? '',
                        'ownership_percentage' => $ownerData['ownership_percentage'] ?? 0,
                        'is_company' => $ownerData['is_company'] ?? false,
                        'ssn_or_itin' => $ownerData['ssn_or_itin'] ?? null,
                        'email' => $ownerData['email'] ?? null,
                        'phone' => $ownerData['phone'] ?? null,
                        'address' => $ownerData['address'] ?? null,
                    ]);
                }
            }
            
            if (isset($data['addresses']) && is_array($data['addresses'])) {
                foreach ($data['addresses'] as $addressData) {
                    CompanyAddress::create([
                        'company_id' => $companyId,
                        'type' => $addressData['type'] ?? 'registered',
                        'street_address' => $addressData['street_address'] ?? '',
                        'city' => $addressData['city'] ?? '',
                        'state' => $addressData['state'] ?? '',
                        'zip_code' => $addressData['zip_code'] ?? '',
                        'country' => $addressData['country'] ?? '',
                        'is_active' => true,
                    ]);
                }
            }
            
            return $order;
        });
    }
    
    public function calculateTotal(Order $order): array
    {
        // Load relationships if not already loaded
        $order->loadMissing(['pricingPlan', 'state']);
        
        $basePrice = $order->pricingPlan->base_price ?? 0;
        $stateFee = $order->state->formation_fee ?? 0;
        $subtotal = $basePrice + $stateFee - $order->discount_amount;
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

