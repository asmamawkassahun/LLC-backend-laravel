<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CompanyOwner;
use App\Models\Country;
use App\Models\MarketplaceOrder;
use App\Models\Order;
use App\Models\PricingPlan;
use App\Models\PromoCode;
use App\Models\Service;
use App\Models\ServicePricing;
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
                'company_owner_ids' => [], // Will be populated after owners are created
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

            // Step 5: Service Storage
            $servicePricingTotal = 0;
            $serviceData = [];
            
            if (isset($data['services']) && is_array($data['services'])) {
                // Map frontend service keys to database field names, pricing names, and display names
                $serviceMapping = [
                    'ein' => ['field' => 'ein', 'pricing_name' => 'ein', 'display_name' => 'EIN'],
                    'itin' => ['field' => 'itin', 'pricing_name' => 'itin', 'display_name' => 'ITIN'],
                    'website' => ['field' => 'website', 'pricing_name' => 'website', 'display_name' => 'Website'],
                    'domainHosting' => ['field' => 'domain_hosting', 'pricing_name' => 'domain_hosting', 'display_name' => 'Domain Hosting'],
                    'businessEmail' => ['field' => 'business_email', 'pricing_name' => 'business_email', 'display_name' => 'Business Email'],
                ];

                
                // Filter only services that are true
                $selectedServices = array_filter($data['services'], function($value) {
                    return $value === true;
                });
                
                if (!empty($selectedServices)) {
                    // Get service pricing names for lookup (use lowercase database names)
                    $servicePricingNames = array_map(function($key) use ($serviceMapping) {
                        return $serviceMapping[$key]['pricing_name'] ?? $key;
                    }, array_keys($selectedServices));
                    
                    // Get pricing for selected services
                    $servicePricing = ServicePricing::whereIn('name', $servicePricingNames)->get();
                    $servicePricingTotal = $servicePricing->sum('price');
                    
                    // Build service data with "pending_display_name" format
                    foreach ($selectedServices as $key => $value) {
                        if (isset($serviceMapping[$key])) {
                            $fieldName = $serviceMapping[$key]['field'];
                            $displayName = $serviceMapping[$key]['display_name'];
                            $serviceData[$fieldName] = 'pending ' . $displayName . $companyId;
                        }
                    }
                }
            }

            // Step 6: Order Storage
            $orderNumber = $this->generateOrderNumber();
            
            // Calculate totals from relationships
            $basePriceValue = $pricingPlan->base_price;
            $stateFee = $state ? ($state->formation_fee ?? 0) : 0;
            $subtotal = $basePriceValue + $stateFee + $servicePricingTotal;
            $discountAmount = 0;
            
            if (isset($data['promo_code'])) {
                $promoCode = PromoCode::where('code', $data['promo_code'])->first();
                if ($promoCode && $this->isPromoCodeValid($promoCode, $subtotal, $user->id)) {
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
                'status' => OrderStatus::PENDING,
                'payment_status' => PaymentStatus::UNPAID,
                'metadata' => $data['metadata'] ?? [],
            ]);
            
            // Step 6: Update company with order_id
            $company->update(['order_id' => $order->id]);
            
            // Step 7: Create Service record if any services are selected
            if (!empty($serviceData)) {
                Service::create(array_merge([
                    'company_id' => $companyId,
                ], $serviceData));
            }
            
            // Step 8: Company Address and Owner Storage
            $ownerIds = [];
            if (isset($data['owners']) && is_array($data['owners'])) {
                foreach ($data['owners'] as $ownerData) {
                    $owner = CompanyOwner::create([
                        'company_id' => $companyId,
                        'full_name' => $ownerData['full_name'] ?? '',
                        'ownership_percentage' => $ownerData['ownership_percentage'] ?? 0,
                        'is_company' => $ownerData['is_company'] ?? false,
                        'email' => $ownerData['email'] ?? null,
                        'phone' => $ownerData['phone'] ?? null,
                        'address' => $ownerData['address'] ?? null,
                    ]);
                    $ownerIds[] = $owner->id;
                }
                // Update company with owner IDs
                $company->update(['company_owner_ids' => $ownerIds]);
            }
            
            if (isset($data['addresses']) && is_array($data['addresses'])) {
                foreach ($data['addresses'] as $addressData) {
                    // Check if using registered agent address - handle various formats
                    $useRegisteredAgent = false;
                    if (isset($addressData['use_registered_agent'])) {
                        $value = $addressData['use_registered_agent'];
                        $useRegisteredAgent = ($value === true || $value === 'true' || $value === 1 || $value === '1' || $value === 'True');
                    }
                    
                    // If using registered agent, we must have the ID
                    if ($useRegisteredAgent) {
                        if (!isset($addressData['registered_agent_address_id']) || !$addressData['registered_agent_address_id']) {
                            throw new \Exception('Registered agent address ID is required when using registered agent address');
                        }
                        
                        // Use the registered agent address ID sent from frontend
                        $registeredAgentAddressId = (int) $addressData['registered_agent_address_id'];
                        
                        // Verify the registered agent address exists and is active
                        $registeredAgentAddress = \App\Models\registeredAgentAddress::where('id', $registeredAgentAddressId)
                            ->where('is_active', true)
                            ->first();
                        
                        if (!$registeredAgentAddress) {
                            throw new \Exception("Registered agent address with ID {$registeredAgentAddressId} not found or is not active");
                        }
                        
                        // Create address with registered agent reference only
                        CompanyAddress::create([
                            'company_id' => $companyId,
                            'registered_agent_address_id' => $registeredAgentAddressId,
                            'type' => $addressData['type'] ?? 'registered',
                            // Address fields MUST be null when using registered agent
                            'street_address' => null,
                            'city' => null,
                            'state' => null,
                            'zip_code' => null,
                            'country' => null,
                            'is_active' => true,
                        ]);
                    } else {
                        // Regular address entry - NOT using registered agent
                        CompanyAddress::create([
                            'company_id' => $companyId,
                            'registered_agent_address_id' => null,
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

                    // Create new owners and collect their IDs
                    $ownerIds = [];
                    foreach ($data['owners'] as $ownerData) {
                        $owner = CompanyOwner::create([
                            'company_id' => $order->company->id,
                            'full_name' => $ownerData['full_name'] ?? '',
                            'ownership_percentage' => $ownerData['ownership_percentage'] ?? 0,
                            'is_company' => $ownerData['is_company'] ?? false,
                            'email' => $ownerData['email'] ?? null,
                            'phone' => $ownerData['phone'] ?? null,
                            'address' => $ownerData['address'] ?? null,
                        ]);
                        $ownerIds[] = $owner->id;
                    }
                    // Update company with new owner IDs
                    $order->company->update(['company_owner_ids' => $ownerIds]);
                }
            }

            // Step 6: Update Company Addresses (delete old, create new)
            if (isset($data['addresses']) && is_array($data['addresses'])) {
                if ($order->company) {
                    // Delete existing addresses
                    $order->company->addresses()->delete();

                    // Create new addresses
                    foreach ($data['addresses'] as $addressData) {
                        // Check if using registered agent address - handle various formats
                        $useRegisteredAgent = false;
                        if (isset($addressData['use_registered_agent'])) {
                            $value = $addressData['use_registered_agent'];
                            $useRegisteredAgent = ($value === true || $value === 'true' || $value === 1 || $value === '1' || $value === 'True');
                        }
                        
                        // If using registered agent, we must have the ID
                        if ($useRegisteredAgent) {
                            if (!isset($addressData['registered_agent_address_id']) || !$addressData['registered_agent_address_id']) {
                                throw new \Exception('Registered agent address ID is required when using registered agent address');
                            }
                            
                            // Use the registered agent address ID sent from frontend
                            $registeredAgentAddressId = (int) $addressData['registered_agent_address_id'];
                            
                            // Verify the registered agent address exists and is active
                            $registeredAgentAddress = \App\Models\registeredAgentAddress::where('id', $registeredAgentAddressId)
                                ->where('is_active', true)
                                ->first();
                            
                            if (!$registeredAgentAddress) {
                                throw new \Exception("Registered agent address with ID {$registeredAgentAddressId} not found or is not active");
                            }
                            
                            // Create address with registered agent reference only
                            CompanyAddress::create([
                                'company_id' => $order->company->id,
                                'registered_agent_address_id' => $registeredAgentAddressId,
                                'type' => $addressData['type'] ?? 'registered',
                                // Address fields MUST be null when using registered agent
                                'street_address' => null,
                                'city' => null,
                                'state' => null,
                                'zip_code' => null,
                                'country' => null,
                                'is_active' => true,
                            ]);
                        } else {
                            // Regular address entry - NOT using registered agent
                            CompanyAddress::create([
                                'company_id' => $order->company->id,
                                'registered_agent_address_id' => null,
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
            }

            // Step 7: Update Service record
            $servicePricingTotal = 0;
            
            if (isset($data['services']) && is_array($data['services']) && $order->company) {
                // Map frontend service keys to database field names, pricing names, and display names
                $serviceMapping = [
                    'ein' => ['field' => 'ein', 'pricing_name' => 'ein', 'display_name' => 'EIN'],
                    'itin' => ['field' => 'itin', 'pricing_name' => 'itin', 'display_name' => 'ITIN'],
                    'website' => ['field' => 'website', 'pricing_name' => 'website', 'display_name' => 'Website'],
                    'domainHosting' => ['field' => 'domain_hosting', 'pricing_name' => 'domain_hosting', 'display_name' => 'Domain Hosting'],
                    'businessEmail' => ['field' => 'business_email', 'pricing_name' => 'business_email', 'display_name' => 'Business Email'],
                ];
                
                // Filter only services that are true
                $selectedServices = array_filter($data['services'], function($value) {
                    return $value === true;
                });
                
                // Get service pricing names for lookup (use lowercase database names)
                $servicePricingNames = array_map(function($key) use ($serviceMapping) {
                    return $serviceMapping[$key]['pricing_name'] ?? $key;
                }, array_keys($selectedServices));
                
                // Get pricing for selected services
                if (!empty($servicePricingNames)) {
                    $servicePricing = ServicePricing::whereIn('name', $servicePricingNames)->get();
                    $servicePricingTotal = $servicePricing->sum('price');
                }
                
                // Build service data with "pending_display_name" format for selected services
                // and null for unselected services
                $serviceData = [];
                foreach ($serviceMapping as $key => $mapping) {
                    $fieldName = $mapping['field'];
                    if (isset($selectedServices[$key]) && $selectedServices[$key] === true) {
                        $displayName = $mapping['display_name'];
                        $serviceData[$fieldName] = 'pending ' . $displayName.$order->company->id;
                    } else {
                        $serviceData[$fieldName] = null;
                    }
                }
                
                // Update or create service record
                $service = $order->company->service;
                if ($service) {
                    // Update existing service record
                    $service->update($serviceData);
                } else {
                    // Create new service record only if at least one service is selected
                    if (!empty($selectedServices)) {
                        Service::create(array_merge([
                            'company_id' => $order->company->id,
                        ], $serviceData));
                    }
                }
            }

            // Step 8: Update Order
            // Reload pricing plan and state to get updated values
            $pricingPlan = PricingPlan::find($pricingPlanId);
            $state = $stateId ? State::find($stateId) : null;

            $basePriceValue = $pricingPlan->base_price ?? 0;
            $stateFee = $state ? ($state->formation_fee ?? 0) : 0;
            $subtotal = $basePriceValue + $stateFee + $servicePricingTotal;
            $discountAmount = $order->discount_amount; // Preserve existing discount

            // Handle promo code if provided
            if (isset($data['promo_code'])) {
                $promoCode = PromoCode::where('code', $data['promo_code'])->first();
                if ($promoCode && $this->isPromoCodeValid($promoCode, $subtotal, $order->user_id)) {
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
        
        // Check if user has already used this promo code (in orders OR marketplace_orders)
        if ($order->user_id) {
            $hasUsedInOrders = Order::where('user_id', $order->user_id)
                ->where('promo_code_id', $promoCode->id)
                ->exists();
            
            $hasUsedInMarketplaceOrders = MarketplaceOrder::where('user_id', $order->user_id)
                ->where('promo_code_id', $promoCode->id)
                ->exists();
            
            if ($hasUsedInOrders || $hasUsedInMarketplaceOrders) {
                throw new \Exception('You have already used this promo code');
            }
        }
        
        if (!$this->isPromoCodeValid($promoCode, $order->subtotal, $order->user_id)) {
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
        
        if ($status === OrderStatus::CONFIRMED) {
            $order->update(['completed_at' => now()]);
            
            // Update company status to FORMED when order is confirmed
            if ($order->company) {
                $order->company->update(['status' => CompanyStatus::FORMED]);
            }
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
    
    public function isPromoCodeValid(PromoCode $promoCode, float $subtotal, ?int $userId = null): bool
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
        
        // Check if user has already used this promo code (in orders OR marketplace_orders)
        if ($userId) {
            $hasUsedInOrders = Order::where('user_id', $userId)
                ->where('promo_code_id', $promoCode->id)
                ->exists();
            
            $hasUsedInMarketplaceOrders = MarketplaceOrder::where('user_id', $userId)
                ->where('promo_code_id', $promoCode->id)
                ->exists();
            
            if ($hasUsedInOrders || $hasUsedInMarketplaceOrders) {
                return false; // User already used this code
            }
        }
        
        return true;
    }
    
    public function calculateDiscount(PromoCode $promoCode, float $subtotal): float
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

