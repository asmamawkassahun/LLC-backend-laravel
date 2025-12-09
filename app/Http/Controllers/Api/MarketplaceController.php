<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceService;
use App\Models\Order;
use App\Models\PromoCode;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
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
            'company_id' => 'nullable|exists:companies,id',
            'promo_code' => 'nullable|string|exists:promo_codes,code',
        ]);

        $user = $request->user();
        $service = MarketplaceService::findOrFail($validated['service_id']);

        return DB::transaction(function () use ($validated, $user, $service) {
            // Calculate subtotal
            $subtotal = $service->price ?? 0;
            $discountAmount = 0;
            $promoCodeId = null;

            // Handle promo code if provided
            if (isset($validated['promo_code'])) {
                $promoCode = PromoCode::where('code', $validated['promo_code'])->first();
                
                if ($promoCode) {
                    // Check if user has already used this promo code
                    $hasUsedInOrders = Order::where('user_id', $user->id)
                        ->where('promo_code_id', $promoCode->id)
                        ->exists();
                    
                    $hasUsedInMarketplaceOrders = MarketplaceOrder::where('user_id', $user->id)
                        ->where('promo_code_id', $promoCode->id)
                        ->exists();
                    
                    if ($hasUsedInOrders || $hasUsedInMarketplaceOrders) {
                        return response()->json([
                            'message' => 'You have already used this promo code'
                        ], 422);
                    }

                    // Validate promo code
                    if ($this->orderService->isPromoCodeValid($promoCode, $subtotal, $user->id)) {
                        $discountAmount = $this->orderService->calculateDiscount($promoCode, $subtotal);
                        $subtotal -= $discountAmount;
                        $promoCodeId = $promoCode->id;
                        
                        // Increment usage count
                        $promoCode->increment('used_count');
                    }
                }
            }

            // Create marketplace order
            $marketplaceOrder = MarketplaceOrder::create([
                'user_id' => $user->id,
                'order_id' => null, // Set this if you create an Order first
                'marketplace_service_id' => $service->id,
                'promo_code_id' => $promoCodeId,
                'company_id' => $validated['company_id'] ?? null,
                'status' => 'pending',
                'requirements_met' => false,
            ]);

            return response()->json([
                'message' => 'Marketplace service ordered successfully',
                'data' => $marketplaceOrder
            ], 201);
        });
    }
}
