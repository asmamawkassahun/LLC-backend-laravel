<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\CompanyOwner;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $stats = [
            'total_orders' => Order::count(),
            'total_revenue' => Order::where('payment_status', 'paid')->sum('total_amount'),
            'total_users' => User::count(),
            'total_company_owners' => CompanyOwner::distinct('full_name')->count('full_name'),
            'total_companies' => Company::count(),
            'pending_orders' => Order::where('status', 'pending_payment')->count(),
            'paid_orders' => Order::where('payment_status', 'paid')->count(),
            'active_users' => User::where('is_active', true)->count(),
            'pending_companies' => Company::where('status', 'pending')->count(),
        ];

        return response()->json($stats);
    }

    public function recentOrders(): JsonResponse
    {
        $orders = Order::with(['user', 'company', 'pricingPlan'])
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'data' => \App\Http\Resources\OrderResource::collection($orders)
        ]);
    }

    public function recentUsers(): JsonResponse
    {
        $users = User::with('profile')
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'data' => \App\Http\Resources\UserResource::collection($users)
        ]);
    }

    public function revenueChart(): JsonResponse
    {
        $revenue = Order::where('payment_status', 'paid')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($revenue);
    }
}
