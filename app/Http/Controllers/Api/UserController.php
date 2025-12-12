<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Mail\EmailVerificationCodeMail;
use App\Models\PasswordResetCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()->load('profile')));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
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

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify current password by decrypting
        try {
            $decryptedPassword = Crypt::decryptString($user->password);
            if ($decryptedPassword !== $request->current_password) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        // Check if new password is different from current password
            if ($decryptedPassword === $request->password) {
                throw ValidationException::withMessages([
                    'password' => ['The new password must be different from your current password.'],
                ]);
            }
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        // Update password (will be encrypted automatically by model setter)
        $user->update([
            'password' => $request->password
        ]);

        return response()->json([
            'message' => 'Password updated successfully'
        ], 200);
    }

    /**
     * Send verification code to current email
     */
    public function sendCurrentEmailVerificationCode(Request $request): JsonResponse
    {
        $user = $request->user();

        // Invalidate any existing unused codes for this email
        PasswordResetCode::where('email', $user->email)
            ->where('used', false)
            ->update(['used' => true]);

        // Generate a 4-digit code
        $code = str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT);

        // Create new verification code (expires in 15 minutes)
        PasswordResetCode::create([
            'email' => $user->email,
            'code' => $code,
            'expires_at' => Carbon::now()->addMinutes(15),
            'used' => false,
        ]);

        // Send email with verification code
        Mail::to($user->email)->send(new EmailVerificationCodeMail($code, $user->name, 'update'));

        return response()->json([
            'message' => 'Verification code sent to your email.',
        ]);
    }

    /**
     * Verify code for current email
     */
    public function verifyCurrentEmailCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:4',
        ]);

        $user = $request->user();

        // Find valid verification code
        $verificationCode = PasswordResetCode::where('email', $user->email)
            ->where('code', $request->code)
            ->where('used', false)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (!$verificationCode) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 400);
        }

        // Mark code as used
        $verificationCode->markAsUsed();

        return response()->json([
            'message' => 'Email verified successfully.',
            'verified' => true,
        ]);
    }

    /**
     * Update email address (after verifying current email)
     */
    public function updateEmail(Request $request): JsonResponse
    {
        $request->validate([
            'new_email' => 'required|email|unique:users,email',
        ]);

        $user = $request->user();

        // Check if new email is different from current
        if ($user->email === $request->new_email) {
            return response()->json([
                'message' => 'New email must be different from your current email.',
            ], 400);
        }

        // Temporarily store new email (we'll verify it in step 3)
        // For now, we'll just update it and mark email as unverified
        $user->email = $request->new_email;
        $user->email_verified_at = null;
        $user->save();

        return response()->json([
            'message' => 'Email updated. Please verify your new email address.',
            'email' => $user->email,
        ]);
    }

    /**
     * Send verification code to new email
     */
    public function sendNewEmailVerificationCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = $request->user();

        // Verify the email belongs to the user
        if ($user->email !== $request->email) {
            return response()->json([
                'message' => 'Email does not match your account.',
            ], 400);
        }

        // Invalidate any existing unused codes for this email
        PasswordResetCode::where('email', $user->email)
            ->where('used', false)
            ->update(['used' => true]);

        // Generate a 4-digit code
        $code = str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT);

        // Create new verification code (expires in 15 minutes)
        PasswordResetCode::create([
            'email' => $user->email,
            'code' => $code,
            'expires_at' => Carbon::now()->addMinutes(15),
            'used' => false,
        ]);

        // Send email with verification code
        Mail::to($user->email)->send(new EmailVerificationCodeMail($code, $user->name, 'verify'));

        return response()->json([
            'message' => 'Verification code sent to your new email.',
        ]);
    }

    /**
     * Verify new email code and complete email update
     */
    public function verifyNewEmailCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:4',
        ]);

        $user = $request->user();

        // Find valid verification code
        $verificationCode = PasswordResetCode::where('email', $user->email)
            ->where('code', $request->code)
            ->where('used', false)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (!$verificationCode) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 400);
        }

        // Mark code as used
        $verificationCode->markAsUsed();

        // Mark email as verified
        $user->email_verified_at = Carbon::now();
        $user->save();

        // Invalidate all other unused codes for this email
        PasswordResetCode::where('email', $user->email)
            ->where('used', false)
            ->where('id', '!=', $verificationCode->id)
            ->update(['used' => true]);

        return response()->json([
            'message' => 'Email updated and verified successfully.',
            'user' => new UserResource($user->load('profile')),
        ]);
    }
}
