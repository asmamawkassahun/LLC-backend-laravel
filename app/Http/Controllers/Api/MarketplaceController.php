<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
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
        $request->validate([
            'service_id' => 'required|exists:marketplace_services,id',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        // Create order logic here
        // This would integrate with OrderService

        return response()->json(['message' => 'Marketplace service ordered successfully'], 201);
    }
}
