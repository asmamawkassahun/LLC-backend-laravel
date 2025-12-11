<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceService;
use App\Models\Order;
use App\Models\PromoCode;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceController extends Controller
{
    protected $orderService;
    protected $paymentService;

    public function __construct(OrderService $orderService, PaymentService $paymentService)
    {
        $this->orderService = $orderService;
        $this->paymentService = $paymentService;
    }

    public function index(): JsonResponse
    {
        $services = MarketplaceService::where('is_active', true)->get();

        return response()->json($services);
    }

    public function show($id): JsonResponse
    {
        $service = MarketplaceService::findOrFail($id);

        return response()->json($service);
    }

    public function order(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:marketplace_services,id',
            'promo_code' => 'nullable|string|exists:promo_codes,code',
        ]);

        $user = $request->user();
        $service = MarketplaceService::findOrFail($validated['service_id']);

        return DB::transaction(function () use ($validated, $user, $service) {
            // Get primary company for the user
            $companyIds = $user->orders()
                ->whereNotNull('company_id')
                ->pluck('company_id')
                ->unique();
            
            $primaryCompany = \App\Models\Company::whereIn('id', $companyIds)
                ->where('is_primary', true)
                ->first();
            
            if (!$primaryCompany) {
                return response()->json([
                    'message' => 'No primary company found. Please create a company first.'
                ], 422);
            }

            // Calculate subtotal
            $subtotal = $service->price ?? 0;
            $discountAmount = 0;
            $promoCode = null;

            // Handle promo code if provided
            if (isset($validated['promo_code'])) {
                $promoCode = PromoCode::where('code', $validated['promo_code'])->first();
                
                if ($promoCode) {
                    // Check if user has already used this promo code in any order
                    $hasUsedInOrders = Order::where('user_id', $user->id)
                        ->where('promo_code_id', $promoCode->id)
                        ->exists();
                    
                    if ($hasUsedInOrders) {
                        return response()->json([
                            'message' => 'You have already used this promo code'
                        ], 422);
                    }

                    // Validate promo code (don't increment usage yet - will be done after payment success)
                    if ($this->orderService->isPromoCodeValid($promoCode, $subtotal, $user->id)) {
                        $discountAmount = $this->orderService->calculateDiscount($promoCode, $subtotal);
                        $subtotal -= $discountAmount;
                        // Note: Usage count will be incremented after payment is successful
                    } else {
                        return response()->json([
                            'message' => 'Invalid or expired promo code'
                        ], 422);
                    }
                } else {
                    return response()->json([
                        'message' => 'Promo code not found'
                    ], 422);
                }
            }

            // Generate unique service order number (will be used when order is created after payment)
            $serviceOrderNumber = $this->generateServiceOrderNumber();

            // Store order data in metadata - order will be created after payment success
            $orderData = [
                'user_id' => $user->id,
                'service_order_number' => $serviceOrderNumber,
                'marketplace_service_id' => $service->id,
                'company_id' => $primaryCompany->id,
                'status' => 'pending',
                'requirements_met' => false,
                'promo_code_id' => $promoCode->id ?? null,
                'discount_amount' => $discountAmount,
                'total_amount' => $subtotal,
            ];

            // Initialize Chapa payment with order data (order will be created after payment success)
            $paymentResult = $this->paymentService->initializeChapaPaymentForMarketplace(
                $orderData,
                [
                    'email' => $user->email,
                    'first_name' => $user->name ?? 'Customer',
                    'last_name' => '',
                    'phone_number' => $user->phone ?? null,
                ],
                $subtotal
            );

            return response()->json([
                'message' => 'Payment initialized successfully',
                'discount_amount' => $discountAmount,
                'total_amount' => $subtotal,
                'checkout_url' => $paymentResult['checkout_url'],
                'payment_id' => $paymentResult['payment_id'],
                'tx_ref' => $paymentResult['tx_ref'],
            ], 201);
        });
    }

    /**
     * Get files from marketplace orders for the authenticated user
     */
    public function orders(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $orders = MarketplaceOrder::where('user_id', $user->id)
            ->with(['marketplaceService'])
            ->select('id', 'service_order_number', 'status', 'created_at', 'delivered_at', 'file', 'marketplace_service_id')
            ->latest()
            ->get();
        
        // Extract and format all files from all orders
        $allFiles = [];
        
        foreach ($orders as $order) {
            if ($order->file && is_array($order->file)) {
                foreach ($order->file as $fileData) {
                    if (is_string($fileData)) {
                        $fileUrl = \Illuminate\Support\Facades\Storage::disk('minio')->url($fileData);
                        $allFiles[] = [
                            'file_path' => $fileData,
                            'file_name' => basename($fileData),
                            'file_url' => $fileUrl,
                            'order_id' => $order->id,
                            'order_number' => $order->service_order_number,
                            'order_status' => $order->status,
                            'service_name' => $order->marketplaceService->name ?? 'Unknown Service',
                            'order_date' => $order->delivered_at ? $order->delivered_at->toISOString() : ($order->created_at ? $order->created_at->toISOString() : null),
                        ];
                    } else {
                        $fileUrl = \Illuminate\Support\Facades\Storage::disk('minio')->url($fileData['file_path']);
                        $allFiles[] = [
                            'file_path' => $fileData['file_path'],
                            'file_name' => $fileData['file_name'] ?? basename($fileData['file_path']),
                            'file_url' => $fileUrl,
                            'uploaded_at' => $fileData['uploaded_at'] ?? null,
                            'order_id' => $order->id,
                            'order_number' => $order->service_order_number,
                            'order_status' => $order->status,
                            'service_name' => $order->marketplaceService->name ?? 'Unknown Service',
                            'order_date' => $order->delivered_at ? $order->delivered_at->toISOString() : ($order->created_at ? $order->created_at->toISOString() : null),
                        ];
                    }
                }
            }
        }
        
        return response()->json([
            'data' => $allFiles,
        ]);
    }

    /**
     * Generate a unique service order number
     */
    private function generateServiceOrderNumber(): string
    {
        do {
            $serviceOrderNumber = 'SVC-' . strtoupper(Str::random(10));
        } while (MarketplaceOrder::where('service_order_number', $serviceOrderNumber)->exists());
        
        return $serviceOrderNumber;
    }
}
