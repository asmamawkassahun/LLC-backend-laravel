<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ApplyPromoCodeRequest;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
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
        $perPage = $request->input('per_page', 15);
        $perPage = min(max((int) $perPage, 1), 100); // Limit between 1 and 100

        $orders = $request->user()->orders()
            ->with(['country', 'pricingPlan', 'company', 'state'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => OrderResource::collection($orders->items()),
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
        ]);
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
            ->with([
                'country',
                'pricingPlan',
                'company',
                'company.owners',
                'company.addresses',
                'company.country',
                'company.state',
                'state',
                'payments'
            ])
            ->findOrFail($id);

        return response()->json(new OrderResource($order));
    }

    public function update(UpdateOrderRequest $request, $id): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($id);
        
        $updatedOrder = $this->orderService->updateOrder($order, $request->validated(), $request->user());

        $this->notificationService->sendOrderNotification($updatedOrder, 'order_updated');

        return response()->json(new OrderResource($updatedOrder->load([
            'country',
            'pricingPlan',
            'company',
            'company.owners',
            'company.addresses',
            'company.country',
            'company.state',
            'state'
        ])));
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
