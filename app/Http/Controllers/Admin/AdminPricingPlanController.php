<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PricingPlanResource;
use App\Models\PricingPlan;
use App\Models\Country;
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
            'type' => 'required|string|in:Premium,Basic',
            'base_price' => 'required|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|array',
            'description.*' => 'string',
            'is_active' => 'boolean',
        ]);

        // Get country to generate name
        $country = Country::findOrFail($validated['country_id']);
        
        // Generate name from type and country code (e.g., "Basic_US" or "Premium_UK")
        $countryCode = $this->getCountryCode($country->name);
        $validated['name'] = $validated['type'] . '_' . $countryCode;
        
        $validated['is_active'] = $validated['is_active'] ?? true;

        $plan = PricingPlan::create($validated);

        return response()->json(new PricingPlanResource($plan->load('country')), 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $plan = PricingPlan::findOrFail($id);

        $validated = $request->validate([
            'country_id' => 'sometimes|exists:countries,id',
            'type' => 'sometimes|string|in:Premium,Basic',
            'base_price' => 'sometimes|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|array',
            'description.*' => 'string',
            'is_active' => 'sometimes|boolean',
        ]);

        // Regenerate name if type or country_id changed
        if (isset($validated['type']) || isset($validated['country_id'])) {
            $countryId = $validated['country_id'] ?? $plan->country_id;
            $type = $validated['type'] ?? $this->extractTypeFromName($plan->name);
            
            $country = Country::findOrFail($countryId);
            $countryCode = $this->getCountryCode($country->name);
            $validated['name'] = $type . '_' . $countryCode;
        }

        $plan->update($validated);

        return response()->json(new PricingPlanResource($plan->fresh()->load('country')));
    }

    private function extractTypeFromName(string $name): string
    {
        // Extract type from name (e.g., "Basic_UK" -> "Basic")
        $parts = explode('_', $name);
        return $parts[0] ?? 'Basic';
    }

    private function getCountryCode(string $countryName): string
    {
        // Map common country names to their codes
        $countryCodeMap = [
            'United States' => 'us',
            'United Kingdom' => 'uk',
            'Canada' => 'CA',
            'Australia' => 'AU',
            'Germany' => 'DE',
            'France' => 'FR',
            'Italy' => 'IT',
            'Spain' => 'ES',
            'Netherlands' => 'NL',
            'Belgium' => 'BE',
            'Switzerland' => 'CH',
            'Austria' => 'AT',
            'Sweden' => 'SE',
            'Norway' => 'NO',
            'Denmark' => 'DK',
            'Finland' => 'FI',
            'Portugal' => 'PT',
            'Ireland' => 'IE',
            'Poland' => 'PL',
            'Czech Republic' => 'CZ',
            'Hungary' => 'HU',
            'Romania' => 'RO',
            'Greece' => 'GR',
            'Russia' => 'RU',
            'Japan' => 'JP',
            'South Korea' => 'KR',
            'China' => 'CN',
            'India' => 'IN',
            'Singapore' => 'SG',
            'Malaysia' => 'MY',
            'Thailand' => 'TH',
            'Indonesia' => 'ID',
            'Philippines' => 'PH',
            'Vietnam' => 'VN',
            'Hong Kong' => 'HK',
            'New Zealand' => 'NZ',
            'South Africa' => 'ZA',
            'Brazil' => 'BR',
            'Mexico' => 'MX',
            'Argentina' => 'AR',
            'Chile' => 'CL',
            'Colombia' => 'CO',
            'Peru' => 'PE',
            'Turkey' => 'TR',
            'Saudi Arabia' => 'SA',
            'United Arab Emirates' => 'AE',
            'Israel' => 'IL',
            'Egypt' => 'EG',
            'Nigeria' => 'NG',
            'Kenya' => 'KE',
            'Ghana' => 'GH',
        ];

        // Check if we have a mapping for this country
        if (isset($countryCodeMap[$countryName])) {
            return $countryCodeMap[$countryName];
        }

        // Fallback: Generate code from country name
        // Take first 2 uppercase letters, or first letter of each word for multi-word countries
        $words = explode(' ', $countryName);
        if (count($words) > 1) {
            // Multi-word country: take first letter of first two words
            $code = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        } else {
            // Single word: take first 2 letters
            $code = strtoupper(substr($countryName, 0, 2));
        }

        return $code;
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
