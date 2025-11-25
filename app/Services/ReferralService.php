<?php

namespace App\Services;

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\ReferralCommission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralService
{
    public function createAffiliate($user, float $commissionRate = 10.00): Affiliate
    {
        return DB::transaction(function () use ($user, $commissionRate) {
            $referralCode = $this->generateReferralCode();
            
            return Affiliate::create([
                'user_id' => $user->id,
                'referral_code' => $referralCode,
                'commission_rate' => $commissionRate,
                'status' => AffiliateStatus::ACTIVE,
                'joined_at' => now(),
            ]);
        });
    }
    
    public function calculateCommission(Order $order, Affiliate $affiliate): float
    {
        $commissionAmount = ($order->total_amount * $affiliate->commission_rate) / 100;
        return round($commissionAmount, 2);
    }
    
    public function processCommission(Order $order, Affiliate $affiliate): ReferralCommission
    {
        return DB::transaction(function () use ($order, $affiliate) {
            $commissionAmount = $this->calculateCommission($order, $affiliate);
            
            $commission = ReferralCommission::create([
                'affiliate_id' => $affiliate->id,
                'order_id' => $order->id,
                'referred_user_id' => $order->user_id,
                'commission_amount' => $commissionAmount,
                'status' => CommissionStatus::PENDING,
            ]);
            
            $affiliate->increment('total_earnings', $commissionAmount);
            $affiliate->increment('pending_earnings', $commissionAmount);
            
            return $commission;
        });
    }
    
    private function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Affiliate::where('referral_code', $code)->exists());
        
        return $code;
    }
}

