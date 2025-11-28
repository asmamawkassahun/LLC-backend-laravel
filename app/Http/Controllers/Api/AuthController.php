<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Generate access and refresh tokens for a user.
     */
    private function generateTokens(User $user): array
    {
        // Access token expires in 1 hour
        $accessToken = $user->createToken(
            'access-token',
            ['*'],
            now()->addHour()
        );

        // Refresh token expires in 7 days
        $refreshToken = $user->createToken(
            'refresh-token',
            ['refresh'],
            now()->addDays(1)
        );

        return [
            'access_token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600, // 1 hour in seconds
        ];
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'country' => $request->country,
        ]);

        // if ($request->has('profile')) {
        //     $user->profile()->create($request->profile);
        // }

        $user->profile()->create([
            'user_id' => $user->id,            
        ]);

        $tokens = $this->generateTokens($user);

        Mail::to($user->email)->send(new WelcomeEmail($user));

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => $tokens['token_type'],
            'expires_in' => $tokens['expires_in'],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $tokens = $this->generateTokens($user);

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => $tokens['token_type'],
            'expires_in' => $tokens['expires_in'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Delete current access token
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        // Delete all tokens (access and refresh) for this user
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out from all devices successfully']);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        // Find the refresh token
        $token = PersonalAccessToken::findToken($request->refresh_token);

        // Validate token exists
        if (!$token) {
            return response()->json(['message' => 'Invalid refresh token'], 401);
        }

        // Check if token has 'refresh' ability
        if (!$token->can('refresh')) {
            return response()->json(['message' => 'Token cannot be used for refresh'], 401);
        }

        // Check if token is expired
        if ($token->expires_at && $token->expires_at->isPast()) {
            $token->delete(); // Clean up expired token
            return response()->json(['message' => 'Refresh token expired'], 401);
        }

        // Get the user from the token
        $user = $token->tokenable;

        // Delete old access tokens for this user (optional - for security)
        $user->tokens()
            ->where('name', 'access-token')
            ->where('id', '!=', $token->id)
            ->delete();

        // Create new access token
        $accessToken = $user->createToken(
            'access-token',
            ['*'],
            now()->addHour()
        );

        return response()->json([
            'access_token' => $accessToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600, // 1 hour in seconds
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()->load('profile')));
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->only(['name', 'phone', 'country', 'timezone']));

        if ($request->hasAny(['date_of_birth', 'nationality', 'address_line1'])) {
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                $request->only(['date_of_birth', 'nationality', 'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country'])
            );
        }

        return response()->json(new UserResource($user->load('profile')));
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent']);
    }

    public function verify(Request $request, $id, $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link'], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        $user->markEmailAsVerified();

        return response()->json(['message' => 'Email verified successfully']);
    }
}
