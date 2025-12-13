<?php

namespace App\Services;

// Add these imports at the top
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\MarketplaceOrder;
use App\Models\Affiliate;
use App\Models\ReferralCommission;
use App\Services\ReferralService;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;


class PaymentService
{
    protected ?StripeClient $stripe = null;
    protected ReferralService $referralService;
    
    public function __construct(ReferralService $referralService)
    {
        // Don't initialize Stripe here - only initialize when needed
        $this->referralService = $referralService;
    }
    
    protected function getStripeClient(): StripeClient
    {
        if ($this->stripe === null) {
            $secretKey = config('services.stripe.secret');
            if (!$secretKey) {
                throw new \Exception('Stripe secret key not configured');
            }
            $this->stripe = new StripeClient($secretKey);
        }
        return $this->stripe;
    }
    
    public function processStripePayment(Order $order, string $paymentToken): Payment
    {
        return DB::transaction(function () use ($order, $paymentToken) {
            try {
                $charge = $this->getStripeClient()->charges->create([
                    'amount' => (int) ($order->total_amount * 100),
                    'currency' => 'usd',
                    'source' => $paymentToken,
                    'description' => "Order #{$order->order_number}",
                ]);
                
                $payment = $this->createPayment($order, [
                    'payment_method' => 'stripe',
                    'payment_provider' => 'stripe',
                    'transaction_id' => $charge->id,
                    'status' => PaymentStatus::PAID,
                    'paid_at' => now(),
                ]);
                
                $order->update([
                    'payment_status' => PaymentStatus::PAID,
                    // 'status' => OrderStatus::PAID,
                    'paid_at' => now(),
                    'payment_method' => 'stripe',
                    'payment_reference' => $charge->id,
                ]);
                
                // Commission will be processed when order status is confirmed
                
                return $payment;
            } catch (\Exception $e) {
                $this->createPayment($order, [
                    'payment_method' => 'stripe',
                    'payment_provider' => 'stripe',
                    'status' => PaymentStatus::FAILED,
                    'failure_reason' => $e->getMessage(),
                ]);
                
                throw $e;
            }
        });
    }
    
    public function createPayment(Order $order, array $data): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'amount' => $order->total_amount,
            'currency' => 'USD',
            'payment_method' => $data['payment_method'],
            'payment_provider' => $data['payment_provider'] ?? null,
            'transaction_id' => $data['transaction_id'] ?? null,
            'status' => $data['status'] ?? PaymentStatus::PENDING,
            'failure_reason' => $data['failure_reason'] ?? null,
            'paid_at' => $data['paid_at'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }
    
    public function processRefund(Order $order, ?float $amount = null): Payment
    {
        return DB::transaction(function () use ($order, $amount) {
            $refundAmount = $amount ?? $order->total_amount;
            
            if ($order->payment_provider === 'stripe' && $order->payment_reference) {
                try {
                    $refund = $this->getStripeClient()->refunds->create([
                        'charge' => $order->payment_reference,
                        'amount' => (int) ($refundAmount * 100),
                    ]);
                    
                    $payment = $this->createPayment($order, [
                        'payment_method' => $order->payment_method,
                        'payment_provider' => 'stripe',
                        'transaction_id' => $refund->id,
                        'status' => PaymentStatus::REFUNDED,
                        'amount' => $refundAmount,
                    ]);
                    
                    $order->update([
                        'payment_status' => PaymentStatus::REFUNDED,
                    ]);
                    
                    return $payment;
                } catch (\Exception $e) {
                    throw new \Exception('Refund failed: ' . $e->getMessage());
                }
            }
            
            throw new \Exception('Payment provider not supported for refund');
        });
    }
    
    public function verifyPayment(Payment $payment): bool
    {
        if ($payment->payment_provider === 'stripe' && $payment->transaction_id) {
            try {
                $charge = $this->getStripeClient()->charges->retrieve($payment->transaction_id);
                return $charge->paid && $charge->status === 'succeeded';
            } catch (\Exception $e) {
                return false;
            }
        }
        
        return false;
    }



    // Chapa payment gateway
    public function initializeChapaPayment(Order $order, array $customerData): array
    {
        $secretKey = config('services.chapa.secret_key');
        if (!$secretKey) {
            throw new \Exception('Chapa secret key not configured');
        }
        
        $txRef = $order->order_number . '_' . time() . '_' . uniqid();
        $amountInETB = $order->total_amount * 55; // USD to ETB conversion

        // Ensure config values are strings
        $appUrl = config('app.url', 'http://localhost:8000');
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        
        // Remove trailing slashes if present
        $appUrl = rtrim($appUrl, '/');
        $frontendUrl = rtrim($frontendUrl, '/');
        
        $payload = [
            'amount' => round($amountInETB, 2),
            'currency' => 'ETB',
            'email' => $customerData['email'],
            'first_name' => $customerData['first_name'],
            'last_name' => $customerData['last_name'] ?? '',
            // 'phone_number' => $customerData['phone_number'] ?? null,
            'tx_ref' => $txRef,
            'callback_url' => $appUrl . '/api/v1/payments/chapa/callback',
            'return_url' => $frontendUrl . '/payment/success?tx_ref=' . $txRef,
            'meta' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        ];

        // Only add phone_number if it exists and is not empty
if (!empty($customerData['phone_number'])) {
    $payload['phone_number'] = $customerData['phone_number'];
}
        
        Log::info('Chapa payment initialization', ['order_id' => $order->id, 'tx_ref' => $txRef]);
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.chapa.co/v1/transaction/initialize', $payload);
            
            if ($response->successful()) {
                $responseData = $response->json();
                if (isset($responseData['status']) && $responseData['status'] === 'success') {
                    $checkoutUrl = $responseData['data']['checkout_url'] ?? null;
                    
                    if (!$checkoutUrl) {
                        Log::error('Chapa checkout URL missing', ['response' => $responseData]);
                        throw new \Exception('Checkout URL not received from Chapa');
                    }
                    
                    $payment = $this->createPayment($order, [
                        'payment_method' => 'chapa',
                        'payment_provider' => 'chapa',
                        'transaction_id' => $txRef,
                        'status' => PaymentStatus::PENDING,
                        'metadata' => [
                            'checkout_url' => $checkoutUrl,
                            'chapa_response' => $responseData,
                            'amount_etb' => $amountInETB,
                        ],
                    ]);
                    
                    Log::info('Chapa payment initialized successfully', ['payment_id' => $payment->id, 'tx_ref' => $txRef]);
                    
                    return [
                        'checkout_url' => $checkoutUrl,
                        'payment_id' => $payment->id,
                        'tx_ref' => $txRef,
                    ];
                } else {
                    $errorMessage = $responseData['message'] ?? 'Chapa initialization failed';
                    Log::error('Chapa initialization failed', [
                        'order_id' => $order->id,
                        'response' => $responseData
                    ]);
                    throw new \Exception($errorMessage);
                }
            } else {
                $errorMessage = $response->json('message') ?? 'Failed to initialize Chapa payment';
                Log::error('Chapa payment initialization HTTP error', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                throw new \Exception($errorMessage);
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Chapa connection error', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            throw new \Exception('Failed to connect to Chapa payment gateway. Please try again.');
        } catch (\Exception $e) {
            Log::error('Chapa payment initialization exception', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function initializeChapaPaymentForMarketplace(array $orderData, array $customerData, float $totalAmount): array
    {
        $secretKey = config('services.chapa.secret_key');
        if (!$secretKey) {
            throw new \Exception('Chapa secret key not configured');
        }
        
        $txRef = 'MPO_' . $orderData['service_order_number'] . '_' . time() . '_' . uniqid();
        $amountInETB = $totalAmount * 55; // USD to ETB conversion

        // Ensure config values are strings
        $appUrl = config('app.url', 'http://localhost:8000');
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        
        // Remove trailing slashes if present
        $appUrl = rtrim($appUrl, '/');
        $frontendUrl = rtrim($frontendUrl, '/');
        
        $payload = [
            'amount' => round($amountInETB, 2),
            'currency' => 'ETB',
            'email' => $customerData['email'],
            'first_name' => $customerData['first_name'],
            'last_name' => $customerData['last_name'] ?? '',
            'tx_ref' => $txRef,
            'callback_url' => $appUrl . '/api/v1/payments/chapa/callback',
            'return_url' => $frontendUrl . '/payment/success?tx_ref=' . $txRef,
            'meta' => [
                'order_data' => $orderData,
                'service_order_number' => $orderData['service_order_number'],
                'type' => 'marketplace_order',
            ],
        ];

        // Only add phone_number if it exists and is not empty
        if (!empty($customerData['phone_number'])) {
            $payload['phone_number'] = $customerData['phone_number'];
        }
        
        Log::info('Chapa payment initialization for marketplace order', ['service_order_number' => $orderData['service_order_number'], 'tx_ref' => $txRef]);
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.chapa.co/v1/transaction/initialize', $payload);
            
            if ($response->successful()) {
                $responseData = $response->json();
                if (isset($responseData['status']) && $responseData['status'] === 'success') {
                    $checkoutUrl = $responseData['data']['checkout_url'] ?? null;
                    
                    if (!$checkoutUrl) {
                        Log::error('Chapa checkout URL missing for marketplace order', ['response' => $responseData]);
                        throw new \Exception('Checkout URL not received from Chapa');
                    }
                    
                    // Create payment record with order_id as null - order will be created after payment success
                    $payment = Payment::create([
                        'order_id' => null,
                        'user_id' => $orderData['user_id'],
                        'amount' => $totalAmount,
                        'currency' => 'USD',
                        'payment_method' => 'chapa',
                        'payment_provider' => 'chapa',
                        'transaction_id' => $txRef,
                        'status' => PaymentStatus::PENDING,
                        'metadata' => [
                            'order_data' => $orderData,
                            'service_order_number' => $orderData['service_order_number'],
                            'checkout_url' => $checkoutUrl,
                            'chapa_response' => $responseData,
                            'amount_etb' => $amountInETB,
                            'type' => 'marketplace_order',
                        ],
                    ]);
                    
                    Log::info('Chapa payment initialized successfully for marketplace order', ['payment_id' => $payment->id, 'tx_ref' => $txRef]);
                    
                    return [
                        'checkout_url' => $checkoutUrl,
                        'payment_id' => $payment->id,
                        'tx_ref' => $txRef,
                    ];
                } else {
                    $errorMessage = $responseData['message'] ?? 'Failed to initialize Chapa payment';
                    Log::error('Chapa initialization failed for marketplace order', [
                        'service_order_number' => $orderData['service_order_number'],
                        'response' => $responseData
                    ]);
                    throw new \Exception($errorMessage);
                }
            } else {
                $errorMessage = $response->json('message') ?? 'Failed to initialize Chapa payment';
                Log::error('Chapa payment initialization HTTP error for marketplace order', [
                    'service_order_number' => $orderData['service_order_number'],
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                throw new \Exception($errorMessage);
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Chapa connection error for marketplace order', ['service_order_number' => $orderData['service_order_number'] ?? 'unknown', 'error' => $e->getMessage()]);
            throw new \Exception('Failed to connect to Chapa payment gateway. Please try again.');
        } catch (\Exception $e) {
            Log::error('Chapa payment initialization exception for marketplace order', ['service_order_number' => $orderData['service_order_number'] ?? 'unknown', 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function verifyChapaPayment(string $txRef): ?Payment
    {
        return DB::transaction(function () use ($txRef) {
            $payment = Payment::where('transaction_id', $txRef)
                ->where('payment_provider', 'chapa')
                ->first();
            
            if (!$payment) {
                Log::warning('Chapa payment not found', ['tx_ref' => $txRef]);
                return null;
            }
            
            // Check if payment is already completed
            if ($payment->status === PaymentStatus::PAID) {
                Log::info('Chapa payment already completed', ['payment_id' => $payment->id, 'tx_ref' => $txRef]);
                return $payment;
            }
            
            $secretKey = config('services.chapa.secret_key');
            if (!$secretKey) {
                Log::error('Chapa secret key not configured for verification');
                return null;
            }
            
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $secretKey,
                ])->timeout(30)->get("https://api.chapa.co/v1/transaction/verify/{$txRef}");
                
                // Log the full response for debugging
                Log::info('Chapa verification API response', [
                    'tx_ref' => $txRef,
                    'http_status' => $response->status(),
                    'response_body' => $response->body(),
                    'response_json' => $response->json(),
                ]);
                
                if ($response->successful()) {
                    $responseData = $response->json();
                    
                    // Log the response structure
                    Log::info('Chapa verification response data', [
                        'tx_ref' => $txRef,
                        'response_status' => $responseData['status'] ?? 'not_set',
                        'response_data' => $responseData,
                    ]);
                    
                    if (isset($responseData['status']) && $responseData['status'] === 'success') {
                        $data = $responseData['data'] ?? [];
                        
                        // Log the data status
                        Log::info('Chapa payment data status', [
                            'tx_ref' => $txRef,
                            'data_status' => $data['status'] ?? 'not_set',
                            'full_data' => $data,
                        ]);
                        
                        if (isset($data['status']) && $data['status'] === 'success') {
                            // Payment is successful - update it
                            $payment->update([
                                'status' => PaymentStatus::PAID,
                                'paid_at' => now(),
                            ]);
                            
                            // Check if this is a marketplace order payment
                            $metadata = $payment->metadata ?? [];
                            $isMarketplaceOrder = isset($metadata['type']) && $metadata['type'] === 'marketplace_order';
                            
                            if ($isMarketplaceOrder && isset($metadata['order_data'])) {
                                // Create marketplace order after successful payment
                                $orderData = $metadata['order_data'];
                                
                                // Check if order already exists (in case of duplicate callbacks)
                                $existingOrder = MarketplaceOrder::where('service_order_number', $orderData['service_order_number'])->first();
                                
                                if ($existingOrder) {
                                    if ($existingOrder->status === 'pending') {
                                        Log::info('Marketplace order already created and pending', [
                                            'marketplace_order_id' => $existingOrder->id,
                                            'service_order_number' => $orderData['service_order_number']
                                        ]);
                                        return $payment;
                                    }
                                    
                                    // Update existing order status and amount
                                    $existingOrder->update([
                                        'status' => 'pending',
                                        'amount' => $orderData['total_amount'] ?? null,
                                    ]);
                                    
                                    // Update payment with marketplace_order_id if not already set
                                    if (!$payment->marketplace_order_id) {
                                        $payment->update([
                                            'marketplace_order_id' => $existingOrder->id,
                                        ]);
                                    }
                                    
                                    Log::info('Marketplace order status updated to pending', [
                                        'marketplace_order_id' => $existingOrder->id,
                                        'service_order_number' => $orderData['service_order_number']
                                    ]);
                                } else {
                                    // Create new marketplace order with status "pending" after payment
                                    $marketplaceOrder = MarketplaceOrder::create([
                                        'user_id' => $orderData['user_id'],
                                        'service_order_number' => $orderData['service_order_number'],
                                        'marketplace_service_id' => $orderData['marketplace_service_id'],
                                        'amount' => $orderData['total_amount'] ?? null,
                                        'company_id' => $orderData['company_id'],
                                        'status' => 'pending',
                                        'requirements_met' => $orderData['requirements_met'] ?? false,
                                    ]);
                                    
                                    // Update payment with marketplace_order_id
                                    $payment->update([
                                        'marketplace_order_id' => $marketplaceOrder->id,
                                    ]);
                                    
                                    // Increment promo code usage if applicable
                                    if (isset($orderData['promo_code']) && $orderData['promo_code']) {
                                        $promoCode = \App\Models\PromoCode::where('code', $orderData['promo_code'])->first();
                                        if ($promoCode) {
                                            $promoCode->increment('used_count');
                                            Log::info('Promo code usage incremented after successful payment', [
                                                'promo_code' => $promoCode->code,
                                                'service_order_number' => $orderData['service_order_number']
                                            ]);
                                        }
                                    }
                                    
                                    Log::info('Marketplace order created after successful payment with pending status', [
                                        'marketplace_order_id' => $marketplaceOrder->id,
                                        'service_order_number' => $orderData['service_order_number'],
                                        'tx_ref' => $txRef
                                    ]);
                                    
                                    // Send notification for marketplace order creation
                                    try {
                                        $notificationService = app(NotificationService::class);
                                        $notificationService->sendMarketplaceOrderCreatedNotification($marketplaceOrder);
                                        Log::info('Marketplace order creation notification sent', [
                                            'marketplace_order_id' => $marketplaceOrder->id,
                                        ]);
                                    } catch (\Exception $e) {
                                        Log::error('Failed to send marketplace order creation notification', [
                                            'marketplace_order_id' => $marketplaceOrder->id,
                                            'error' => $e->getMessage(),
                                        ]);
                                    }
                                }
                                
                                Log::info('Chapa payment verified successfully for marketplace order', [
                                    'payment_id' => $payment->id,
                                    'service_order_number' => $orderData['service_order_number'],
                                    'tx_ref' => $txRef
                                ]);
                                
                                return $payment;
                            } else {
                                // Handle regular order
                                $order = $payment->order;
                                if (!$order) {
                                    Log::error('Order not found for payment', ['payment_id' => $payment->id]);
                                    return null;
                                }
                                
                                if ($order->payment_status->value === 'paid') {
                                    Log::info('Order already paid', ['order_id' => $order->id]);
                                    return $payment;
                                }
                                
                                $order->update([
                                    'payment_status' => PaymentStatus::PAID,
                                    // 'status' => OrderStatus::PAID,
                                    'paid_at' => now(),
                                    'payment_method' => 'chapa',
                                    'payment_reference' => $txRef,
                                ]);
                                
                                // Commission will be processed when order status is confirmed
                                
                                // Increment promo code usage if applicable
                                $orderMetadata = $order->metadata ?? [];
                                if (isset($orderMetadata['promo_code']) && $orderMetadata['promo_code']) {
                                    $promoCode = \App\Models\PromoCode::where('code', $orderMetadata['promo_code'])->first();
                                    if ($promoCode) {
                                        $promoCode->increment('used_count');
                                        Log::info('Promo code usage incremented after successful payment', [
                                            'promo_code' => $promoCode->code,
                                            'order_id' => $order->id
                                        ]);
                                    }
                                } elseif ($order->promo_code_id) {
                                    // Fallback for old orders that still have promo_code_id
                                    $promoCode = \App\Models\PromoCode::find($order->promo_code_id);
                                    if ($promoCode) {
                                        $promoCode->increment('used_count');
                                        Log::info('Promo code usage incremented after successful payment (legacy)', [
                                            'promo_code_id' => $promoCode->id,
                                            'order_id' => $order->id
                                        ]);
                                    }
                                }
                                
                                Log::info('Chapa payment verified successfully', [
                                    'payment_id' => $payment->id,
                                    'order_id' => $order->id,
                                    'tx_ref' => $txRef
                                ]);
                                
                                return $payment;
                            }
                        } else {
                            // Payment exists but status is not "successful"
                            Log::warning('Chapa payment not successful', [
                                'tx_ref' => $txRef,
                                'status' => $data['status'] ?? 'unknown',
                                'full_data' => $data,
                            ]);
                        }
                    } else {
                        Log::warning('Chapa API response status not success', [
                            'tx_ref' => $txRef,
                            'response_status' => $responseData['status'] ?? 'not_set',
                            'response_data' => $responseData,
                        ]);
                    }
                } else {
                    Log::error('Chapa verification API failed', [
                        'tx_ref' => $txRef,
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Chapa verification exception', [
                    'tx_ref' => $txRef,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return null;
            }
            
            return null;
        });
    }

    /**
     * Process referral commission when an order is paid AND confirmed
     */
    public function processReferralCommission(Order $order): void
    {
        try {
            // Check if payment is paid
            if ($order->payment_status !== PaymentStatus::PAID) {
                return;
            }

            // Check if order is confirmed
            if ($order->status !== OrderStatus::CONFIRMED) {
                return;
            }

            // Check if the user was referred by an affiliate
            if (!$order->user->referral_code) {
                return;
            }

            // Find the affiliate by referral code
            $affiliate = Affiliate::where('referral_code', $order->user->referral_code)
                ->where('status', \App\Enums\AffiliateStatus::ACTIVE)
                ->first();

            if (!$affiliate) {
                return;
            }

            // Check if commission already exists for this order
            $existingCommission = ReferralCommission::where('order_id', $order->id)
                ->where('affiliate_id', $affiliate->id)
                ->first();

            if ($existingCommission) {
                return; // Commission already processed
            }

            // Process the commission
            $this->referralService->processCommission($order, $affiliate);
        } catch (\Exception $e) {
            // Log error but don't fail the payment
            Log::error('Failed to process referral commission', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

}

