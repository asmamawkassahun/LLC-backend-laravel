<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Mail\PasswordResetMail;
use App\Mail\WelcomeEmail;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
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
        // Password will be encrypted automatically by the model's setPasswordAttribute
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'phone' => $request->phone,
            'country' => $request->country,
            'referral_code' => $request->referral_code,
        ]);

        // if ($request->has('profile')) {
        //     $user->profile()->create($request->profile);
        // }

        $user->profile()->create([
            'user_id' => $user->id,            
        ]);

        $tokens = $this->generateTokens($user);

        try {
            Mail::to($user->email)->send(new WelcomeEmail($user));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Welcome email failed to send: '.$e->getMessage());
        }

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

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user account is active
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been suspended. Please contact support for assistance.'],
            ]);
        }

        // Decrypt and compare password
        try {
            $decryptedPassword = Crypt::decryptString($user->password);
            if ($decryptedPassword !== $request->password) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }
        } catch (\Exception $e) {
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

    /**
     * Send password reset code to user's email
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Always return success to prevent email enumeration
        if (!$user) {
            return response()->json([
                'message' => 'If the email exists, a password reset code has been sent.',
            ]);
        }

        // Invalidate any existing unused codes for this email
        PasswordResetCode::where('email', $user->email)
            ->where('used', false)
            ->update(['used' => true]);

        // Generate a 6-digit code
        $code = str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Create new reset code (expires in 15 minutes)
        PasswordResetCode::create([
            'email' => $user->email,
            'code' => $code,
            'expires_at' => Carbon::now()->addMinutes(15),
            'used' => false,
        ]);

        // Send email with reset code
        Mail::to($user->email)->send(new PasswordResetMail($code, $user->name));

        return response()->json([
            'message' => 'If the email exists, a password reset code has been sent.',
        ]);
    }

    /**
     * Verify password reset code (without resetting password)
     */
    public function verifyResetCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Invalid email or code.',
            ], 400);
        }

        // Find valid reset code
        $resetCode = PasswordResetCode::where('email', $request->email)
            ->where('code', $request->code)
            ->where('used', false)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (!$resetCode) {
            return response()->json([
                'message' => 'Invalid or expired reset code.',
            ], 400);
        }

        // Code is valid - return success (don't mark as used yet, will be marked when password is reset)
        return response()->json([
            'message' => 'Code verified successfully.',
            'verified' => true,
        ]);
    }

    /**
     * Reset password using the verification code
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Invalid email or code.',
            ], 400);
        }

        // Find valid reset code
        $resetCode = PasswordResetCode::where('email', $request->email)
            ->where('code', $request->code)
            ->where('used', false)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (!$resetCode) {
            return response()->json([
                'message' => 'Invalid or expired reset code.',
            ], 400);
        }

        // Update password
        $user->password = $request->password;
        $user->save();

        // Mark code as used
        $resetCode->markAsUsed();

        // Invalidate all other unused codes for this email
        PasswordResetCode::where('email', $user->email)
            ->where('used', false)
            ->where('id', '!=', $resetCode->id)
            ->update(['used' => true]);

        return response()->json([
            'message' => 'Password has been reset successfully. You can now login with your new password.',
        ]);
    }
}
