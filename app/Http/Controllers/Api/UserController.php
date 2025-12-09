<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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
}
