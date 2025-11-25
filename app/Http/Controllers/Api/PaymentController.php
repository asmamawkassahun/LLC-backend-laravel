<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ProcessPaymentRequest;
use App\Http\Requests\Payment\RefundRequest;
use App\Http\Resources\PaymentResource;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
