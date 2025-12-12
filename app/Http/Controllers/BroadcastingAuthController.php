<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Admin;
use Laravel\Sanctum\PersonalAccessToken;

class BroadcastingAuthController extends Controller
{
    /**
     * Authenticate the request for channel access.
     * This handles both User and Admin tokens.
     */
    public function authenticate(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        
        Log::info('Broadcasting auth request received', [
            'channel' => $request->input('channel_name'),
            'socket_id' => $request->input('socket_id'),
            'has_token' => $request->hasHeader('Authorization'),
            'token_prefix' => $token ? substr($token, 0, 20) . '...' : 'none',
        ]);
        
        if (!$token) {
            Log::warning('Broadcasting auth: No token provided');
            return response()->json(['message' => 'Unauthorized - No token'], 403);
        }

        // Find the token in the database
        $personalAccessToken = PersonalAccessToken::findToken($token);
        
        if (!$personalAccessToken) {
            Log::warning('Broadcasting auth: Token not found', ['token_prefix' => substr($token, 0, 10)]);
            return response()->json(['message' => 'Unauthorized - Invalid token'], 403);
        }

        // Get the model that owns the token (User or Admin)
        $tokenable = $personalAccessToken->tokenable;
        
        if (!$tokenable) {
            Log::warning('Broadcasting auth: Token has no tokenable model');
            return response()->json(['message' => 'Unauthorized - Invalid token owner'], 403);
        }

        Log::info('Broadcasting auth: Token validated', [
            'model_type' => get_class($tokenable),
            'model_id' => $tokenable->id,
            'channel' => $request->input('channel_name'),
        ]);

        // Set the authenticated user for this request
        // We need to set it both via setUserResolver and Auth facade
        // so that Broadcast::auth() can access it
        $request->setUserResolver(function () use ($tokenable) {
            return $tokenable;
        });
        
        // Also set it in the Auth facade so auth()->user() works
        Auth::setUser($tokenable);

        // Now call Broadcast::auth() which will use the authenticated user
        try {
            // Log before calling Broadcast::auth to see what we're passing
            Log::info('Broadcasting auth: About to call Broadcast::auth', [
                'channel' => $request->input('channel_name'),
                'authenticated_user_type' => get_class($tokenable),
                'authenticated_user_id' => $tokenable->id,
            ]);
            
            $response = Broadcast::auth($request);
            Log::info('Broadcasting auth: Success', ['channel' => $request->input('channel_name')]);
            // Broadcast::auth() returns an array, we need to convert it to JsonResponse
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Broadcasting auth: Error', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'channel' => $request->input('channel_name'),
                'authenticated_user_type' => get_class($tokenable),
                'authenticated_user_id' => $tokenable->id,
            ]);
            
            // If the error message is empty, provide more context
            $errorMessage = $e->getMessage() ?: 'Channel authorization failed - check channel routes';
            return response()->json(['message' => 'Authorization failed: ' . $errorMessage], 403);
        }
    }
}
