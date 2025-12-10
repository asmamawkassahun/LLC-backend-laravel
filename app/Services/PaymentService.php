<?php

namespace App\Services;

// Add these imports at the top
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;


class PaymentService
{
    protected ?StripeClient $stripe = null;
    
    public function __construct()
    {
        // Don't initialize Stripe here - only initialize when needed
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
                            
                            Log::info('Chapa payment verified successfully', [
                                'payment_id' => $payment->id,
                                'order_id' => $order->id,
                                'tx_ref' => $txRef
                            ]);
                            
                            return $payment;
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

}

