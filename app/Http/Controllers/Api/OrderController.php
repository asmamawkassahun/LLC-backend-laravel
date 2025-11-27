<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ApplyPromoCodeRequest;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\CompanyFormationService;
use App\Services\NotificationService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected CompanyFormationService $companyFormationService,
        protected NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()
            ->with(['country', 'pricingPlan', 'company', 'state'])
            ->latest()
            ->paginate(15);

        return response()->json(OrderResource::collection($orders));
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->createOrder($request->validated(), $request->user());

        $this->notificationService->sendOrderNotification($order, 'order_created');

        return response()->json(new OrderResource($order->load(['country', 'pricingPlan', 'company', 'state'])), 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $order = $request->user()->orders()
            ->with(['country', 'pricingPlan', 'company', 'state', 'payments'])
            ->findOrFail($id);

        return response()->json(new OrderResource($order));
    }

    public function applyPromoCode(ApplyPromoCodeRequest $request, $id): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($id);
        $totals = $this->orderService->applyPromoCode($order, $request->code);

        return response()->json([
            'message' => 'Promo code applied successfully',
            'totals' => $totals,
        ]);
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($id);

        if (!in_array($order->status->value, ['draft', 'pending_payment'])) {
            return response()->json(['message' => 'Order cannot be cancelled'], 422);
        }

        $request->validate(['reason' => 'nullable|string|max:500']);

        $this->orderService->updateOrderStatus($order, \App\Enums\OrderStatus::CANCELLED);
        $order->update(['cancellation_reason' => $request->reason]);

        return response()->json(['message' => 'Order cancelled successfully']);
    }
}
