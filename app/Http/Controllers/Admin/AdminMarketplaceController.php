<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMarketplaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MarketplaceService::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }


        $perPage = $request->input('per_page', 20);
        $services = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $services->items(),
            'current_page' => $services->currentPage(),
            'last_page' => $services->lastPage(),
            'per_page' => $services->perPage(),
            'total' => $services->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:marketplace_services,name',
            'description' => 'required|string',
            'requirements' => 'nullable|array',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        $service = MarketplaceService::create($validated);

        return response()->json($service, 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $service = MarketplaceService::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:marketplace_services,name,' . $id,
            'description' => 'sometimes|string',
            'requirements' => 'nullable|array',
            'price' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $service->update($validated);

        return response()->json($service->fresh());
    }

    public function destroy($id): JsonResponse
    {
        $service = MarketplaceService::findOrFail($id);

        // Check if service has orders
        if ($service->marketplaceOrders()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete marketplace service that has associated orders.'
            ], 422);
        }

        $service->delete();

        return response()->json(['message' => 'Marketplace service deleted successfully']);
    }

    public function toggleStatus($id): JsonResponse
    {
        $service = MarketplaceService::findOrFail($id);
        $service->update(['is_active' => !$service->is_active]);

        return response()->json($service->fresh());
    }
}
