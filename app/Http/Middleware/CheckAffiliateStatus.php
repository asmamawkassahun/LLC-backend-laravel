<?php

namespace App\Http\Middleware;

use App\Enums\AffiliateStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAffiliateStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if ($user && $user->affiliate) {
            if ($user->affiliate->status !== AffiliateStatus::ACTIVE) {
                return response()->json([
                    'message' => 'Your affiliate account is not active.',
                ], 403);
            }
        }

        return $next($request);
    }
}
