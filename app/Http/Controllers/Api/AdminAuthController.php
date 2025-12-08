<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    /**
     * Generate access and refresh tokens for an admin.
     */
    private function generateTokens(Admin $admin): array
    {
        // Access token expires in 8 hours
        $accessToken = $admin->createToken(
            'admin-access-token',
            ['*'],
            now()->addHours(8)
        );

        // Refresh token expires in 7 days
        $refreshToken = $admin->createToken(
            'admin-refresh-token',
            ['refresh'],
            now()->addDays(7)
        );

        return [
            'access_token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => 28800, // 8 hours in seconds
        ];
    }

    public function login(AdminLoginRequest $request): JsonResponse
    {
        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$admin->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        // Update last login
        $admin->update(['last_login_at' => now()]);

        $tokens = $this->generateTokens($admin);

        return response()->json([
            'admin' => new AdminResource($admin),
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => $tokens['token_type'],
            'expires_in' => $tokens['expires_in'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(new AdminResource($request->user()));
    }
}
