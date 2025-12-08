<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ApplyPromoCodeRequest;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Requests\Order\GeneratePdfFromFormDataRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\CompanyFormationService;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\OrderPdfService;
use App\Models\registeredAgentAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected CompanyFormationService $companyFormationService,
        protected NotificationService $notificationService,
        protected OrderPdfService $orderPdfService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $perPage = min(max((int) $perPage, 1), 100); // Limit between 1 and 100

        $orders = $request->user()->orders()
            ->with(['country', 'pricingPlan', 'company', 'company.service', 'state'])
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

        return response()->json(new OrderResource($order->load(['country', 'pricingPlan', 'company', 'company.service', 'state'])), 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $order = $request->user()->orders()
            ->with([
                'country',
                'pricingPlan',
                'company',
                'company.owners',
                'company.addresses.registeredAgentAddress',
                'company.country',
                'company.state',
                'company.service',
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
            'company.addresses.registeredAgentAddress',
            'company.country',
            'company.state',
            'company.service',
            'state'
        ])));
    }

    public function getRegisteredAgentAddress(Request $request): JsonResponse
{
    $address = registeredAgentAddress::where('is_active', true)
        ->first();

    if (!$address) {
        return response()->json(['message' => 'No active registered agent address found'], 404);
    }

    return response()->json([
        'data' => [
            'id' => $address->id,
            'address' => $address->address,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
        ]
    ]);
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

    public function destroy(Request $request, $id): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($id);
        
        // Prevent deletion of paid orders
        if ($order->payment_status->value === 'paid') {
            return response()->json([
                'message' => 'Cannot delete paid orders. Please cancel the order instead.'
            ], 422);
        }
        
        // Prevent deletion of orders that are processing or completed
        if (in_array($order->status->value, ['processing', 'completed'])) {
            return response()->json([
                'message' => 'Cannot delete orders that are processing or completed.'
            ], 422);
        }
        
        try {
            // Delete related payments first (if any)
            $order->payments()->delete();
            
            // Delete the order
            $order->delete();
            
            return response()->json([
                'message' => 'Order deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete order: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadOrderSummary(Request $request, $id): Response
    {
        $order = $request->user()->orders()->findOrFail($id);
        
        try {
            $pdf = $this->orderPdfService->generateOrderSummaryPdf($order);
            
            $filename = 'order-summary-' . $order->order_number . '.pdf';
            
            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generatePdfFromFormData(GeneratePdfFromFormDataRequest $request): Response
    {
        try {
            $userEmail = $request->user()->email;
            $pdf = $this->orderPdfService->generateOrderSummaryPdfFromFormData($request->validated(), $userEmail);
            
            $filename = 'order-summary-preview.pdf';
            
            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }
}
