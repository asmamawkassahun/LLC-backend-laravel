<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    
    // Email verification route (public, but signed)
    Route::get('/auth/verify-email/{id}/{hash}', [AuthController::class, 'verify'])
        ->middleware(['signed'])
        ->name('verification.verify');

        // Chapa callback (public - called by Chapa webhook)
        Route::post('/payments/chapa/callback', [PaymentController::class, 'chapaCallback']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // Authentication
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/verify-email', [AuthController::class, 'verifyEmail']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);

        // User
        Route::get('/user', [UserController::class, 'show']);
        Route::put('/user', [UserController::class, 'update']);
        Route::put('/user/change-password', [UserController::class, 'changePassword']);

        // Orders
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle.orders');
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::put('/orders/{id}', [OrderController::class, 'update']);
        Route::post('/orders/{id}/apply-promo-code', [OrderController::class, 'applyPromoCode']);
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
        Route::delete('/orders/{id}', [OrderController::class, 'destroy']);
        Route::get('/registered-agent-address', [OrderController::class, 'getRegisteredAgentAddress']);

        // Companies
        Route::get('/companies', [CompanyController::class, 'index']);
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::get('/companies/{id}', [CompanyController::class, 'show']);
        Route::put('/companies/{id}', [CompanyController::class, 'update']);
        Route::post('/companies/{id}/set-primary', [CompanyController::class, 'setAsPrimary']);

        // Marketplace
        Route::get('/marketplace/services', [MarketplaceController::class, 'index']);
        Route::get('/marketplace/services/{id}', [MarketplaceController::class, 'show']);
        Route::post('/marketplace/order', [MarketplaceController::class, 'order']);

        // Payments
        Route::post('/payments/process', [PaymentController::class, 'process']);
        Route::get('/payments/{id}', [PaymentController::class, 'show']);
        Route::post('/payments/refund', [PaymentController::class, 'refund']);
        // Chapa payment initialization
        Route::post('/payments/chapa/initialize', [PaymentController::class, 'initializeChapa']);
        Route::post('/payments/chapa/verify', [PaymentController::class, 'verifyPayment']);

        // Referrals
        Route::post('/referrals/register', [ReferralController::class, 'register']);
        Route::get('/referrals/dashboard', [ReferralController::class, 'dashboard']);
        Route::get('/referrals/commissions', [ReferralController::class, 'commissions']);

        // Support
        Route::get('/support/tickets', [SupportController::class, 'index']);
        Route::post('/support/tickets', [SupportController::class, 'store']);
        Route::get('/support/tickets/{id}', [SupportController::class, 'show']);
        Route::post('/support/tickets/{id}/reply', [SupportController::class, 'reply']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    });
});
