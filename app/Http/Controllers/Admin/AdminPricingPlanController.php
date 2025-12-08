<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PricingPlanResource;
use App\Models\PricingPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminPricingPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PricingPlan::with('country');

        if ($request->has('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->input('per_page', 20);
        $plans = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => PricingPlanResource::collection($plans->items()),
            'current_page' => $plans->currentPage(),
            'last_page' => $plans->lastPage(),
            'per_page' => $plans->perPage(),
            'total' => $plans->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'base_price' => 'required|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $plan = PricingPlan::create($validated);

        return response()->json(new PricingPlanResource($plan->load('country')), 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $plan = PricingPlan::findOrFail($id);

        $validated = $request->validate([
            'country_id' => 'sometimes|exists:countries,id',
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string',
            'base_price' => 'sometimes|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'features' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $plan->update($validated);

        return response()->json(new PricingPlanResource($plan->fresh()->load('country')));
    }

    public function destroy($id): JsonResponse
    {
        $plan = PricingPlan::findOrFail($id);

        // Check if plan has orders
        if ($plan->orders()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete pricing plan that has associated orders.'
            ], 422);
        }

        $plan->delete();

        return response()->json(['message' => 'Pricing plan deleted successfully']);
    }

    public function toggleStatus($id): JsonResponse
    {
        $plan = PricingPlan::findOrFail($id);
        $plan->update(['is_active' => !$plan->is_active]);

        return response()->json(new PricingPlanResource($plan->fresh()->load('country')));
    }
}
