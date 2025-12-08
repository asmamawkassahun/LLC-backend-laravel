<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $perPage = min(max((int) $perPage, 1), 100); // Limit between 1 and 100
        
        $query = User::with('profile');
        
        // Filter by search if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }
        
        $users = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
        ]);
    }

    public function show($id): JsonResponse
    {
        $user = User::with('profile')->findOrFail($id);

        return response()->json(new UserResource($user));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone' => 'sometimes|nullable|string|max:20',
            'country' => 'sometimes|nullable|string|max:100',
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'is_active' => 'sometimes|boolean',
        ]);

        $user = User::findOrFail($id);
        
        $updateData = $request->only(['name', 'email', 'phone', 'country', 'is_active']);
        
        // Only update password if provided and not empty
        if ($request->filled('password') && $request->password !== '') {
            $updateData['password'] = Hash::make($request->password);
        }
        
        $user->update($updateData);

        return response()->json(new UserResource($user->load('profile')));
    }

    public function deactivate($id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => false]);

        return response()->json(['message' => 'User deactivated successfully']);
    }

    public function destroy($id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
