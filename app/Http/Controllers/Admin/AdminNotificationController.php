<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\MarketplaceOrder;
use App\Models\Payout;
use App\Enums\OrderStatus;
use Illuminate\Http\JsonResponse;

class AdminNotificationController extends Controller
{
    public function counts(): JsonResponse
    {
        // Count new orders (not confirmed)
        $newOrdersCount = Order::where('status', '!=', OrderStatus::CONFIRMED->value)
            ->where('payment_status', 'paid')
            ->count();

        // Count pending marketplace orders
        $pendingMarketplaceOrdersCount = MarketplaceOrder::where('status', 'pending')->count();

        // Count pending payout requests
        $pendingPayoutsCount = Payout::where('status', 'pending')->count();

        $totalCount = $newOrdersCount + $pendingMarketplaceOrdersCount + $pendingPayoutsCount;

        return response()->json([
            'new_orders' => $newOrdersCount,
            'pending_marketplace_orders' => $pendingMarketplaceOrdersCount,
            'pending_payouts' => $pendingPayoutsCount,
            'total' => $totalCount,
        ]);
    }
}

