<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPayoutController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payout::with([
            'affiliate.user',
            'bankAccount'
        ]);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = $request->input('per_page', 20);
        $payouts = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $payouts->items(),
            'current_page' => $payouts->currentPage(),
            'last_page' => $payouts->lastPage(),
            'per_page' => $payouts->perPage(),
            'total' => $payouts->total(),
        ]);
    }

    public function show($id): JsonResponse
    {
        $payout = Payout::with([
            'affiliate.user',
            'bankAccount'
        ])->findOrFail($id);

        return response()->json($payout);
    }

    public function approve($id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $payout = Payout::with('affiliate')->findOrFail($id);

            if ($payout->status !== 'pending') {
                return response()->json([
                    'message' => 'Only pending payouts can be approved'
                ], 422);
            }

            $payout->update([
                'status' => 'completed',
                'processed_at' => now(),
            ]);

            // Update affiliate paid earnings
            $payout->affiliate->increment('paid_earnings', $payout->amount);

            return response()->json([
                'message' => 'Payout approved successfully',
                'data' => $payout->fresh(['affiliate.user', 'bankAccount'])
            ]);
        });
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        return DB::transaction(function () use ($id, $request) {
            $payout = Payout::with('affiliate')->findOrFail($id);

            if ($payout->status !== 'pending') {
                return response()->json([
                    'message' => 'Only pending payouts can be rejected'
                ], 422);
            }

            $payout->update([
                'status' => 'failed',
                'notes' => $request->notes ?? $payout->notes,
            ]);

            // Return the amount back to pending earnings
            $payout->affiliate->increment('pending_earnings', $payout->amount);

            return response()->json([
                'message' => 'Payout rejected successfully',
                'data' => $payout->fresh(['affiliate.user', 'bankAccount'])
            ]);
        });
    }
}

