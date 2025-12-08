<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if user is authenticated and is an Admin model instance
        if (!$user || !($user instanceof \App\Models\Admin)) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        // Check if admin is active
        if (!$user->isActive()) {
            return response()->json([
                'message' => 'Your admin account has been deactivated.'
            ], 403);
        }

        return $next($request);
    }
}
