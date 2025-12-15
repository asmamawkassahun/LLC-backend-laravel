<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\registeredAgentAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRegisteredAgentAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = registeredAgentAddress::with('country');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->input('per_page', 20);
        $addresses = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $addresses->items(),
            'current_page' => $addresses->currentPage(),
            'last_page' => $addresses->lastPage(),
            'per_page' => $addresses->perPage(),
            'total' => $addresses->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'postal_code' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        $address = registeredAgentAddress::create($validated);

        return response()->json($address, 201);
    }

    public function show($id): JsonResponse
    {
        $address = registeredAgentAddress::with('country')->findOrFail($id);

        return response()->json($address);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $address = registeredAgentAddress::findOrFail($id);

        $validated = $request->validate([
            'address' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'state' => 'sometimes|string|max:255',
            'postal_code' => 'sometimes|string|max:255',
            'country_id' => 'sometimes|exists:countries,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $address->update($validated);

        return response()->json($address->fresh());
    }

    public function destroy($id): JsonResponse
    {
        $address = registeredAgentAddress::findOrFail($id);
        $address->delete();

        return response()->json(['message' => 'Registered agent address deleted successfully']);
    }

    public function toggleStatus($id): JsonResponse
    {
        $address = registeredAgentAddress::findOrFail($id);
        $address->update(['is_active' => !$address->is_active]);

        return response()->json($address->fresh());
    }
}
