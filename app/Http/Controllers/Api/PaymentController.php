<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ProcessPaymentRequest;
use App\Http\Requests\Payment\RefundRequest;
use App\Http\Resources\PaymentResource;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Payment;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected NotificationService $notificationService
    ) {}

    public function process(ProcessPaymentRequest $request): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($request->order_id);

        if ($order->payment_status->value !== 'unpaid') {
            return response()->json(['message' => 'Order already paid'], 422);
        }

        try {
            $payment = $this->paymentService->processStripePayment($order, $request->payment_token);
            $this->notificationService->sendPaymentNotification($order, 'order_paid');

            return response()->json(new PaymentResource($payment), 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        $payment = $request->user()->payments()->findOrFail($id);

        return response()->json(new PaymentResource($payment));
    }

    public function refund(RefundRequest $request): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($request->order_id);

        try {
            $payment = $this->paymentService->processRefund($order, $request->amount);

            return response()->json(new PaymentResource($payment));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }



    public function initializeChapa(Request $request): JsonResponse
{
    try {
        $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
        ]);
        
        $order = $request->user()->orders()->findOrFail($request->order_id);
        
        if ($order->payment_status->value !== 'unpaid') {
            return response()->json(['message' => 'Order already paid'], 422);
        }
        
        $result = $this->paymentService->initializeChapaPayment(
            $order,
            [
                'email' => $request->user()->email,
                'first_name' => $request->user()->name ?? 'Customer',
                'last_name' => '',
                'phone_number' => $request->user()->phone ?? null,
            ]
        );
        
        return response()->json($result, 201);
    } catch (ValidationException $e) {
        return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
    } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'Order not found'], 404);
    } catch (\Exception $e) {
        Log::error('Chapa initialization error', [
            'order_id' => $request->input('order_id'),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['message' => $e->getMessage()], 500);
    }
}

public function chapaCallback(Request $request): JsonResponse
{
    Log::info('Chapa callback received', ['data' => $request->all()]);
    
    $txRef = $request->input('tx_ref');
    
    if (!$txRef) {
        Log::warning('Chapa callback missing tx_ref', ['request_data' => $request->all()]);
        return response()->json(['message' => 'Transaction reference missing'], 400);
    }
    
    try {
        $payment = $this->paymentService->verifyChapaPayment($txRef);
        
        if ($payment) {
            // Check if payment status was just updated (not already completed)
            $payment->refresh();
            if ($payment->status->value === 'paid') {
                // Only send notification if order was just paid (check if paid_at was just set)
                $order = $payment->order;
                if ($order && $order->payment_status->value === 'paid') {
                    $this->notificationService->sendPaymentNotification($order, 'order_paid');
                }
            }
            return response()->json(['message' => 'Payment verified successfully'], 200);
        }
        
        Log::warning('Chapa payment verification failed', ['tx_ref' => $txRef]);
        return response()->json(['message' => 'Payment verification failed'], 400);
    } catch (\Exception $e) {
        Log::error('Chapa callback error', [
            'tx_ref' => $txRef,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['message' => 'Error processing callback'], 500);
    }
}

public function verifyPayment(Request $request): JsonResponse
{
    $request->validate([
        'tx_ref' => ['required', 'string'],
    ]);
    
    $txRef = $request->tx_ref;
    Log::info('Payment verification requested', ['tx_ref' => $txRef]);
    
    try {
        // First, check if payment exists in database
        $payment = Payment::where('transaction_id', $txRef)
            ->where('payment_provider', 'chapa')
            ->first();
        
        if (!$payment) {
            Log::warning('Payment not found in database', ['tx_ref' => $txRef]);
            return response()->json([
                'success' => false,
                'message' => 'Payment record not found. Please contact support with transaction reference: ' . $txRef
            ], 404);
        }
        
        Log::info('Payment found', [
            'payment_id' => $payment->id,
            'current_status' => $payment->status->value,
            'tx_ref' => $txRef
        ]);
        
        // Now verify with Chapa
        $verifiedPayment = $this->paymentService->verifyChapaPayment($txRef);
        
        if ($verifiedPayment) {
            $verifiedPayment->refresh();
            if ($verifiedPayment->status->value === 'paid') {
                $order = $verifiedPayment->order;
                if ($order && $order->payment_status->value === 'paid') {
                    $this->notificationService->sendPaymentNotification($order, 'order_paid');
                }
            }
            return response()->json([
                'success' => true,
                'payment_status' => $verifiedPayment->status->value,
                'order_status' => $verifiedPayment->order->payment_status->value ?? null,
            ], 200);
        }
        
        // If verification returned null, check why
        $payment->refresh();
        Log::warning('Chapa verification returned null', [
            'tx_ref' => $txRef,
            'payment_status' => $payment->status->value,
            'payment_id' => $payment->id
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Payment verification failed. Payment status: ' . $payment->status->value,
            'payment_status' => $payment->status->value,
        ], 400);
    } catch (\Exception $e) {
        Log::error('Payment verification error', [
            'tx_ref' => $txRef,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Verification error: ' . $e->getMessage()
        ], 500);
    }
}

}
