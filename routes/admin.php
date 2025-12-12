<?php

use App\Http\Controllers\Admin\AdminActivityLogController;
use App\Http\Controllers\Admin\AdminAffiliateController;
use App\Http\Controllers\Admin\AdminCompanyController;
use App\Http\Controllers\Admin\AdminCountryController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminMarketplaceController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminPricingPlanController;
use App\Http\Controllers\Admin\AdminPromoCodeController;
use App\Http\Controllers\Admin\AdminMaintenanceController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Api\AdminAuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

// Public admin auth routes
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
});

// Protected admin routes
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Auth
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/me', [AdminAuthController::class, 'me']);
    Route::put('/profile', [AdminAuthController::class, 'update']);
    // Orders
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
    Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
    Route::post('/orders/{id}/refund', [AdminOrderController::class, 'refund']);
    Route::get('/orders/{id}/download-summary', [AdminOrderController::class, 'downloadOrderSummary']);

    // Users
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::post('/users/{id}/deactivate', [AdminUserController::class, 'deactivate']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);

    // Companies
    Route::get('/companies', [AdminCompanyController::class, 'index']);
    Route::get('/companies/{id}', [AdminCompanyController::class, 'show']);
    Route::put('/companies/{id}/status', [AdminCompanyController::class, 'updateStatus']);
    Route::post('/companies/{id}/approve', [AdminCompanyController::class, 'approve']);
    Route::get('/companies/{id}/download-summary', [AdminCompanyController::class, 'downloadSummary']);

    // Support
    Route::get('/support/tickets', [AdminSupportController::class, 'index']);
    Route::get('/support/tickets/{id}', [AdminSupportController::class, 'show']);
    Route::post('/support/tickets/{id}/assign', [AdminSupportController::class, 'assign']);
    Route::post('/support/tickets/{id}/reply', [AdminSupportController::class, 'reply']);
    Route::post('/support/tickets/{id}/resolve', [AdminSupportController::class, 'resolve']);
    Route::get('/support/admins', [AdminSupportController::class, 'getAdmins']);

    // Payments
    Route::get('/payments', [AdminPaymentController::class, 'index']);
    Route::get('/payments/{id}', [AdminPaymentController::class, 'show']);

    // Countries
    Route::get('/countries', [AdminCountryController::class, 'index']);

    // Pricing Plans
    Route::get('/pricing-plans', [AdminPricingPlanController::class, 'index']);
    Route::post('/pricing-plans', [AdminPricingPlanController::class, 'store']);
    Route::put('/pricing-plans/{id}', [AdminPricingPlanController::class, 'update']);
    Route::delete('/pricing-plans/{id}', [AdminPricingPlanController::class, 'destroy']);
    Route::post('/pricing-plans/{id}/toggle-status', [AdminPricingPlanController::class, 'toggleStatus']);

    // Promo Codes
    Route::get('/promo-codes', [AdminPromoCodeController::class, 'index']);
    Route::post('/promo-codes', [AdminPromoCodeController::class, 'store']);
    Route::put('/promo-codes/{id}', [AdminPromoCodeController::class, 'update']);
    Route::delete('/promo-codes/{id}', [AdminPromoCodeController::class, 'destroy']);
    Route::post('/promo-codes/{id}/toggle-status', [AdminPromoCodeController::class, 'toggleStatus']);

    // Marketplace
    Route::get('/marketplace/services', [AdminMarketplaceController::class, 'index']);
    Route::post('/marketplace/services', [AdminMarketplaceController::class, 'store']);
    Route::put('/marketplace/services/{id}', [AdminMarketplaceController::class, 'update']);
    Route::delete('/marketplace/services/{id}', [AdminMarketplaceController::class, 'destroy']);
    Route::post('/marketplace/services/{id}/toggle-status', [AdminMarketplaceController::class, 'toggleStatus']);
    Route::get('/marketplace/orders', [AdminMarketplaceController::class, 'orders']);
    Route::post('/marketplace/orders/{id}/accept', [AdminMarketplaceController::class, 'acceptOrder']);
    Route::post('/marketplace/orders/{id}/upload', [AdminMarketplaceController::class, 'uploadFile']);
    Route::get('/marketplace/orders/{id}/files', [AdminMarketplaceController::class, 'getFiles']);
    Route::delete('/marketplace/orders/{id}/files/{fileIndex}', [AdminMarketplaceController::class, 'deleteFile']);
    Route::delete('/marketplace/orders/{id}', [AdminMarketplaceController::class, 'deleteOrder']);

    // Affiliates
    Route::get('/affiliates', [AdminAffiliateController::class, 'index']);
    Route::get('/affiliates/{id}', [AdminAffiliateController::class, 'show']);
    Route::put('/affiliates/{id}/status', [AdminAffiliateController::class, 'updateStatus']);
    Route::put('/affiliates/{id}/commission-rate', [AdminAffiliateController::class, 'updateCommissionRate']);
    Route::get('/affiliates/{id}/commissions', [AdminAffiliateController::class, 'commissions']);

    // Settings
    Route::get('/settings', [AdminSettingsController::class, 'index']);
    Route::put('/settings', [AdminSettingsController::class, 'update']);
    Route::get('/settings/{key}', [AdminSettingsController::class, 'get']);
    Route::put('/settings/{key}', [AdminSettingsController::class, 'set']);

    // Dashboard
    Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);
    Route::get('/dashboard/recent-orders', [AdminDashboardController::class, 'recentOrders']);
    Route::get('/dashboard/recent-users', [AdminDashboardController::class, 'recentUsers']);
    Route::get('/dashboard/revenue-chart', [AdminDashboardController::class, 'revenueChart']);

    // Activity Logs
    Route::get('/activity-logs', [AdminActivityLogController::class, 'index']);
    Route::get('/activity-logs/{id}', [AdminActivityLogController::class, 'show']);

    // Maintenance Mode
    Route::get('/maintenance/status', [AdminMaintenanceController::class, 'status']);
    Route::post('/maintenance/enable', [AdminMaintenanceController::class, 'enable']);
    Route::post('/maintenance/disable', [AdminMaintenanceController::class, 'disable']);

    // Broadcasting authentication for admins
    Broadcast::routes(['middleware' => ['auth:sanctum', 'admin']]);
});

