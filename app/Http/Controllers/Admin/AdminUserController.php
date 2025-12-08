<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'is_active' => 'sometimes|boolean',
        ]);

        $user = User::findOrFail($id);
        $user->update($request->only(['name', 'email', 'is_active']));

        return response()->json(new UserResource($user->load('profile')));
    }

    public function deactivate($id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => false]);

        return response()->json(['message' => 'User deactivated successfully']);
    }
}
