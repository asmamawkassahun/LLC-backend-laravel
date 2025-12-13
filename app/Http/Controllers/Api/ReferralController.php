<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use App\Models\BankAccount;
use App\Models\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    public function stats(Request $request): JsonResponse
    {
        $affiliate = $request->user()->affiliate;

        if (!$affiliate) {
            return response()->json(['message' => 'User is not an affiliate'], 404);
        }

        // Count users referred (users with this affiliate's referral code)
        $usersReferred = \App\Models\User::where('referral_code', $affiliate->referral_code)->count();

        // Count paid users (users who have at least one paid order)
        $paidUsers = \App\Models\User::where('referral_code', $affiliate->referral_code)
            ->whereHas('orders', function ($query) {
                $query->where('payment_status', 'paid');
            })
            ->count();

        return response()->json([
            'users_referred' => $usersReferred,
            'paid_users' => $paidUsers,
            'referral_earnings' => (float) $affiliate->total_earnings,
            'pending_earnings' => (float) $affiliate->pending_earnings,
            'paid_earnings' => (float) $affiliate->paid_earnings,
        ]);
    }

    public function referralLink(Request $request): JsonResponse
    {
        $affiliate = $request->user()->affiliate;

        if (!$affiliate) {
            return response()->json(['message' => 'User is not an affiliate'], 404);
        }

        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $referralLink = rtrim($frontendUrl, '/') . '/register?ref=' . $affiliate->referral_code;

        return response()->json([
            'referral_link' => $referralLink,
            'referral_code' => $affiliate->referral_code,
        ]);
    }

    public function bankAccounts(Request $request): JsonResponse
    {
        $affiliate = $request->user()->affiliate;

        if (!$affiliate) {
            return response()->json(['message' => 'User is not an affiliate'], 404);
        }

        $bankAccounts = $affiliate->bankAccounts()->latest()->get();

        return response()->json($bankAccounts);
    }

    public function createBankAccount(Request $request): JsonResponse
    {
        $affiliate = $request->user()->affiliate;

        if (!$affiliate) {
            return response()->json(['message' => 'User is not an affiliate'], 404);
        }

        $validated = $request->validate([
            'account_holder_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:100',
            'bank_name' => 'required|string|max:255',
            'swift_code' => 'nullable|string|max:50',
            'iban' => 'nullable|string|max:50',
            'routing_number' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:100',
            'is_primary' => 'boolean',
        ]);

        // If this is set as primary, unset other primary accounts
        if ($validated['is_primary'] ?? false) {
            $affiliate->bankAccounts()->update(['is_primary' => false]);
        }

        $bankAccount = $affiliate->bankAccounts()->create($validated);

        return response()->json($bankAccount, 201);
    }

    public function updateBankAccount(Request $request, $id): JsonResponse
    {
        $affiliate = $request->user()->affiliate;

        if (!$affiliate) {
            return response()->json(['message' => 'User is not an affiliate'], 404);
        }

        $bankAccount = $affiliate->bankAccounts()->findOrFail($id);

        $validated = $request->validate([
            'account_holder_name' => 'sometimes|string|max:255',
            'account_number' => 'sometimes|string|max:100',
            'bank_name' => 'sometimes|string|max:255',
            'swift_code' => 'nullable|string|max:50',
            'iban' => 'nullable|string|max:50',
            'routing_number' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:100',
            'is_primary' => 'boolean',
        ]);

        // If this is set as primary, unset other primary accounts
        if (isset($validated['is_primary']) && $validated['is_primary']) {
            $affiliate->bankAccounts()->where('id', '!=', $id)->update(['is_primary' => false]);
        }

        $bankAccount->update($validated);

        return response()->json($bankAccount);
    }

    public function deleteBankAccount(Request $request, $id): JsonResponse
    {
        $affiliate = $request->user()->affiliate;

        if (!$affiliate) {
            return response()->json(['message' => 'User is not an affiliate'], 404);
        }

        $bankAccount = $affiliate->bankAccounts()->findOrFail($id);
        $bankAccount->delete();

        return response()->json(['message' => 'Bank account deleted successfully']);
    }

    public function payouts(Request $request): JsonResponse
    {
        $affiliate = $request->user()->affiliate;

        if (!$affiliate) {
            return response()->json(['message' => 'User is not an affiliate'], 404);
        }

        $payouts = $affiliate->payouts()
            ->with('bankAccount')
            ->latest()
            ->paginate(15);

        return response()->json($payouts);
    }

    public function requestPayout(Request $request): JsonResponse
    {
        $affiliate = $request->user()->affiliate;

        if (!$affiliate) {
            return response()->json(['message' => 'User is not an affiliate'], 404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Check if affiliate has enough pending earnings
        if ($validated['amount'] > $affiliate->pending_earnings) {
            throw ValidationException::withMessages([
                'amount' => ['Insufficient pending earnings. Available: $' . number_format($affiliate->pending_earnings, 2)],
            ]);
        }

        // Get primary bank account if not specified
        $bankAccountId = $validated['bank_account_id'] ?? null;
        if (!$bankAccountId) {
            $primaryBankAccount = $affiliate->bankAccounts()->where('is_primary', true)->first();
            if (!$primaryBankAccount) {
                throw ValidationException::withMessages([
                    'bank_account_id' => ['Please provide a bank account or set a primary bank account'],
                ]);
            }
            $bankAccountId = $primaryBankAccount->id;
        }

        // Verify bank account belongs to affiliate
        $bankAccount = $affiliate->bankAccounts()->findOrFail($bankAccountId);

        $payout = DB::transaction(function () use ($affiliate, $validated, $bankAccountId) {
            // Create payout
            $payout = $affiliate->payouts()->create([
                'bank_account_id' => $bankAccountId,
                'amount' => $validated['amount'],
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Deduct from pending earnings
            $affiliate->decrement('pending_earnings', $validated['amount']);

            return $payout;
        });

        return response()->json($payout->load('bankAccount'), 201);
    }
}
