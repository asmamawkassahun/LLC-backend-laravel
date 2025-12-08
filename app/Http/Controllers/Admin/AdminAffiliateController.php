<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\ReferralCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAffiliateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Affiliate::with('user');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->input('per_page', 20);
        $affiliates = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $affiliates->items(),
            'current_page' => $affiliates->currentPage(),
            'last_page' => $affiliates->lastPage(),
            'per_page' => $affiliates->perPage(),
            'total' => $affiliates->total(),
        ]);
    }

    public function show($id): JsonResponse
    {
        $affiliate = Affiliate::with(['user', 'commissions.order', 'commissions.referredUser'])
            ->findOrFail($id);

        return response()->json($affiliate);
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected,suspended',
        ]);

        $affiliate = Affiliate::findOrFail($id);
        $affiliate->update(['status' => \App\Enums\AffiliateStatus::from($request->status)]);

        return response()->json($affiliate->fresh()->load('user'));
    }

    public function updateCommissionRate(Request $request, $id): JsonResponse
    {
        $request->validate([
            'commission_rate' => 'required|numeric|min:0|max:100',
        ]);

        $affiliate = Affiliate::findOrFail($id);
        $affiliate->update(['commission_rate' => $request->commission_rate]);

        return response()->json($affiliate->fresh()->load('user'));
    }

    public function commissions($id): JsonResponse
    {
        $affiliate = Affiliate::findOrFail($id);
        $commissions = ReferralCommission::where('affiliate_id', $id)
            ->with(['order', 'referredUser'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $commissions->items(),
            'current_page' => $commissions->currentPage(),
            'last_page' => $commissions->lastPage(),
            'per_page' => $commissions->perPage(),
            'total' => $commissions->total(),
        ]);
    }
}
