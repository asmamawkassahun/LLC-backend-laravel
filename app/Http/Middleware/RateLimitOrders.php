<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitOrders
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'orders:' . $request->user()?->id ?? $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many order creation attempts. Please try again later.',
            ], 429);
        }
        
        RateLimiter::hit($key, 60); // 5 attempts per minute
        
        return $next($request);
    }
}
