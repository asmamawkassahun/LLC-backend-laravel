<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(protected ReferralService $referralService) {}

    public function register(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->affiliate) {
            return response()->json(['message' => 'User is already an affiliate'], 422);
        }

        $affiliate = $this->referralService->createAffiliate($user);

        return response()->json($affiliate, 201);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $affiliate = $request->user()->affiliate;

        if (!$affiliate) {
            return response()->json(['message' => 'User is not an affiliate'], 404);
        }

        return response()->json([
            'affiliate' => $affiliate,
            'commissions' => $affiliate->commissions()->with('order')->latest()->paginate(15),
        ]);
    }

    public function commissions(Request $request): JsonResponse
    {
        $affiliate = $request->user()->affiliate;

        if (!$affiliate) {
            return response()->json(['message' => 'User is not an affiliate'], 404);
        }

        $commissions = $affiliate->commissions()
            ->with(['order', 'referredUser'])
            ->latest()
            ->paginate(15);

        return response()->json($commissions);
    }
}
