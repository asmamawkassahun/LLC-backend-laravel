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
            // Check if this is the user's first order
            $isFirstOrder = $user->orders()->count() === 0;
            
            $company = Company::create([
                'user_id' => $user->id,
                'order_id' => null, // Will be updated after order creation
                'name' => $data['company_name'],
                'type' => $data['company_type'],
                'category' => $data['category'] ?? [],
                'country_id' => $countryId,
                'state_id' => $stateId,
                'status' => CompanyStatus::PENDING,
                'is_primary' => $isFirstOrder, // Set to true if this is the first order
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

    public function updateOrder(Order $order, array $data, $user): Order
    {
        return DB::transaction(function () use ($order, $data, $user) {
            // Load existing relationships
            $order->load(['country', 'pricingPlan', 'company', 'state']);

            // Step 1: Country Storage/Update
            $countryName = $data['plan'][0]['countryName'] ?? null;
            if ($countryName) {
                $country = Country::where('name', $countryName)->first();
                if (!$country) {
                    $country = Country::create([
                        'name' => $countryName,
                        'is_active' => true,
                    ]);
                }
                $countryId = $country->id;
            } else {
                $countryId = $order->country_id;
            }

            // Step 2: State Storage/Update
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
                } else {
                    // Update formation fee if changed
                    if ($state->formation_fee != $stateCost) {
                        $state->update(['formation_fee' => $stateCost]);
                    }
                }
                $stateId = $state->id;
            } else {
                $stateId = $order->state_id;
                $state = $order->state;
            }

            // Step 3: Pricing Plan Storage/Update
            $pricingPlanName = $data['plan'][0]['pricingPlan'] ?? null;
            $basePrice = $data['plan'][0]['basePrice'] ?? 0;
            $yearlyPrice = $data['plan'][0]['yearlyPrice'] ?? 0;

            if ($pricingPlanName) {
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
                } else {
                    // Update prices if changed
                    $updateData = [];
                    if ($pricingPlan->base_price != $basePrice) {
                        $updateData['base_price'] = $basePrice;
                    }
                    if ($pricingPlan->yearly_price != $yearlyPrice) {
                        $updateData['yearly_price'] = $yearlyPrice;
                    }
                    if (!empty($updateData)) {
                        $pricingPlan->update($updateData);
                    }
                }
                $pricingPlanId = $pricingPlan->id;
            } else {
                $pricingPlanId = $order->pricing_plan_id;
                $pricingPlan = $order->pricingPlan;
            }

            // Step 4: Company Update
            if ($order->company) {
                $companyUpdateData = [];
                if (isset($data['company_name'])) {
                    $companyUpdateData['name'] = $data['company_name'];
                }
                if (isset($data['company_type'])) {
                    $companyUpdateData['type'] = $data['company_type'];
                }
                if (isset($data['category'])) {
                    $companyUpdateData['category'] = $data['category'];
                }
                if ($order->company->country_id != $countryId) {
                    $companyUpdateData['country_id'] = $countryId;
                }
                if ($order->company->state_id != $stateId) {
                    $companyUpdateData['state_id'] = $stateId;
                }

                if (!empty($companyUpdateData)) {
                    $order->company->update($companyUpdateData);
                }
            }

            // Step 5: Update Company Owners (delete old, create new)
            if (isset($data['owners']) && is_array($data['owners'])) {
                if ($order->company) {
                    // Delete existing owners
                    $order->company->owners()->delete();

                    // Create new owners
                    foreach ($data['owners'] as $ownerData) {
                        CompanyOwner::create([
                            'company_id' => $order->company->id,
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
            }

            // Step 6: Update Company Addresses (delete old, create new)
            if (isset($data['addresses']) && is_array($data['addresses'])) {
                if ($order->company) {
                    // Delete existing addresses
                    $order->company->addresses()->delete();

                    // Create new addresses
                    foreach ($data['addresses'] as $addressData) {
                        CompanyAddress::create([
                            'company_id' => $order->company->id,
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
            }

            // Step 7: Update Order
            // Reload pricing plan and state to get updated values
            $pricingPlan = PricingPlan::find($pricingPlanId);
            $state = $stateId ? State::find($stateId) : null;

            $basePriceValue = $pricingPlan->base_price ?? 0;
            $stateFee = $state ? ($state->formation_fee ?? 0) : 0;
            $subtotal = $basePriceValue + $stateFee;
            $discountAmount = $order->discount_amount; // Preserve existing discount

            // Handle promo code if provided
            if (isset($data['promo_code'])) {
                $promoCode = PromoCode::where('code', $data['promo_code'])->first();
                if ($promoCode && $this->isPromoCodeValid($promoCode, $subtotal)) {
                    $discountAmount = $this->calculateDiscount($promoCode, $subtotal);
                    $subtotal -= $discountAmount;
                }
            } else {
                $subtotal -= $discountAmount;
            }

            $taxAmount = $order->tax_amount; // Preserve existing tax
            $totalAmount = $subtotal + $taxAmount;

            $orderUpdateData = [
                'country_id' => $countryId,
                'pricing_plan_id' => $pricingPlanId,
                'state_id' => $stateId,
                'discount_amount' => $discountAmount,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
            ];

            if (isset($data['metadata'])) {
                $orderUpdateData['metadata'] = $data['metadata'];
            }

            // Preserve order status and payment status - don't update them
            $order->update($orderUpdateData);

            // Reload relationships
            $order->load(['country', 'pricingPlan', 'company', 'state']);
            
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

