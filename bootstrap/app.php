<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        
        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'throttle.orders' => \App\Http\Middleware\RateLimitOrders::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
        
        // Exclude admin routes and maintenance status endpoint from maintenance mode
        $middleware->preventRequestsDuringMaintenance(except: [
            'api/admin/*',
            'api/v1/maintenance/status',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
