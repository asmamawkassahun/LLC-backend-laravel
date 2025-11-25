<?php

use App\Http\Controllers\Admin\AdminCompanyController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    // Orders
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
    Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
    Route::post('/orders/{id}/refund', [AdminOrderController::class, 'refund']);

    // Users
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::post('/users/{id}/deactivate', [AdminUserController::class, 'deactivate']);

    // Companies
    Route::get('/companies', [AdminCompanyController::class, 'index']);
    Route::get('/companies/{id}', [AdminCompanyController::class, 'show']);
    Route::put('/companies/{id}/status', [AdminCompanyController::class, 'updateStatus']);
    Route::post('/companies/{id}/approve', [AdminCompanyController::class, 'approve']);

    // Support
    Route::get('/support/tickets', [AdminSupportController::class, 'index']);
    Route::get('/support/tickets/{id}', [AdminSupportController::class, 'show']);
    Route::post('/support/tickets/{id}/assign', [AdminSupportController::class, 'assign']);
    Route::post('/support/tickets/{id}/reply', [AdminSupportController::class, 'reply']);
    Route::post('/support/tickets/{id}/resolve', [AdminSupportController::class, 'resolve']);
});

