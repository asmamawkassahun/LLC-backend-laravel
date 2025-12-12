<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


// Add this for your private user channel
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Add this for admin private channel
Broadcast::channel('admin.{adminId}', function ($user, $adminId) {
    // Check if user is an admin and matches the adminId
    if ($user instanceof \App\Models\Admin) {
        return (int) $user->id === (int) $adminId;
    }
    return false;
});

// Add general admin support channel - all admins can listen
// Using admin-support instead of admin.support to avoid dot matching issues
Broadcast::channel('admin-support', function ($user) {
    // Check if user is an admin
    Log::info('Admin support channel authorization check', [
        'user_type' => get_class($user),
        'is_admin' => $user instanceof \App\Models\Admin,
        'user_id' => $user->id ?? 'no_id',
    ]);
    
    $result = $user instanceof \App\Models\Admin;
    
    Log::info('Admin support channel authorization result', [
        'result' => $result,
        'user_type' => get_class($user),
    ]);
    
    return $result;
});