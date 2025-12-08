<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PromoCodeResource;
use App\Models\PromoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPromoCodeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PromoCode::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('code')) {
            $query->where('code', 'like', '%' . $request->code . '%');
        }

        $perPage = $request->input('per_page', 20);
        $promoCodes = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => PromoCodeResource::collection($promoCodes->items()),
            'current_page' => $promoCodes->currentPage(),
            'last_page' => $promoCodes->lastPage(),
            'per_page' => $promoCodes->perPage(),
            'total' => $promoCodes->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes,code',
            'type' => 'required|string|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after:valid_from',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['used_count'] = 0;

        $promoCode = PromoCode::create($validated);

        return response()->json(new PromoCodeResource($promoCode), 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $promoCode = PromoCode::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:promo_codes,code,' . $id,
            'type' => 'sometimes|string|in:percentage,fixed',
            'value' => 'sometimes|numeric|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after:valid_from',
            'is_active' => 'sometimes|boolean',
        ]);

        $promoCode->update($validated);

        return response()->json(new PromoCodeResource($promoCode->fresh()));
    }

    public function destroy($id): JsonResponse
    {
        $promoCode = PromoCode::findOrFail($id);

        // Check if promo code has orders
        if ($promoCode->orders()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete promo code that has been used in orders.'
            ], 422);
        }

        $promoCode->delete();

        return response()->json(['message' => 'Promo code deleted successfully']);
    }

    public function toggleStatus($id): JsonResponse
    {
        $promoCode = PromoCode::findOrFail($id);
        $promoCode->update(['is_active' => !$promoCode->is_active]);

        return response()->json(new PromoCodeResource($promoCode->fresh()));
    }
}
