<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If user is authenticated and account is not active
        if ($user && !$user->is_active) {
            // Allow access to support endpoints so suspended users can contact support
            $path = $request->path();
            $isAllowed = false;

            // Check if path matches allowed endpoints for suspended users
            $allowedPatterns = [
                'api/v1/support', // Support tickets
                'api/v1/auth/logout', // Logout
                'api/v1/auth/me', // Get current user
                'api/v1/user', // Get/update user (needed to check status)
            ];

            foreach ($allowedPatterns as $pattern) {
                if (str_starts_with($path, $pattern)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                return response()->json([
                    'message' => 'Your account has been suspended. Please contact support for assistance.',
                    'suspended' => true,
                ], 403);
            }
        }

        return $next($request);
    }
}

