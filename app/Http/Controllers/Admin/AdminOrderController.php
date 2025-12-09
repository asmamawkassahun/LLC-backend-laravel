<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\OrderPdfService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Enums\OrderStatus;

class AdminOrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected PaymentService $paymentService,
        protected OrderPdfService $orderPdfService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $perPage = min(max((int) $perPage, 1), 100); // Limit between 1 and 100
        
        $query = Order::with(['country', 'pricingPlan', 'company', 'state', 'payments'])->where('status', '!=', OrderStatus::CONFIRMED->value);
        
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $orders = $query->latest()->paginate($perPage);
    
        return response()->json([
            'data' => OrderResource::collection($orders->items()),
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
        ]);
    }

    public function show($id): JsonResponse
    {
        $order = Order::with(['country', 'pricingPlan', 'company', 'state', 'payments'])
            ->findOrFail($id);

        return response()->json(new OrderResource($order));
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,pending_payment,paid,confirmed,cancelled',
        ]);

        $order = Order::findOrFail($id);
        $this->orderService->updateOrderStatus($order, \App\Enums\OrderStatus::from($request->status));

        return response()->json(new OrderResource($order->fresh()));
    }

    public function refund(Request $request, $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        try {
            $payment = $this->paymentService->processRefund($order, $request->amount);

            return response()->json(new \App\Http\Resources\PaymentResource($payment));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function downloadOrderSummary($id): Response
    {
        $order = Order::with(['country', 'pricingPlan', 'company', 'company.owners', 'company.addresses', 'state', 'payments'])
            ->findOrFail($id);

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
}
