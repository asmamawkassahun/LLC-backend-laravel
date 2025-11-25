<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class PaymentService
{
    protected StripeClient $stripe;
    
    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }
    
    public function processStripePayment(Order $order, string $paymentToken): Payment
    {
        return DB::transaction(function () use ($order, $paymentToken) {
            try {
                $charge = $this->stripe->charges->create([
                    'amount' => (int) ($order->total_amount * 100),
                    'currency' => 'usd',
                    'source' => $paymentToken,
                    'description' => "Order #{$order->order_number}",
                ]);
                
                $payment = $this->createPayment($order, [
                    'payment_method' => 'stripe',
                    'payment_provider' => 'stripe',
                    'transaction_id' => $charge->id,
                    'status' => PaymentStatus::COMPLETED,
                    'paid_at' => now(),
                ]);
                
                $order->update([
                    'payment_status' => PaymentStatus::PAID,
                    'status' => OrderStatus::PAID,
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
                    $refund = $this->stripe->refunds->create([
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
                        'status' => OrderStatus::REFUNDED,
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
                $charge = $this->stripe->charges->retrieve($payment->transaction_id);
                return $charge->paid && $charge->status === 'succeeded';
            } catch (\Exception $e) {
                return false;
            }
        }
        
        return false;
    }
}

