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
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\HandleCors::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        
        // Configure CORS
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
        
        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'throttle.orders' => \App\Http\Middleware\RateLimitOrders::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'user.active' => \App\Http\Middleware\CheckUserStatus::class,
        ]);
        
        // Exclude admin routes and maintenance status endpoint from maintenance mode
        $middleware->preventRequestsDuringMaintenance(except: [
            'api/admin/*',
            'api/v1/maintenance/status',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Add CORS headers to error responses
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                $response = response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            } elseif ($e instanceof \Illuminate\Auth\AuthenticationException) {
                $response = response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                $response = response()->json([
                    'message' => $e->getMessage(),
                ], $e->getStatusCode());
            } else {
                $response = response()->json([
                    'message' => config('app.debug') ? $e->getMessage() : 'Server Error',
                ], 500);
            }

            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');

            return $response;
        });
    })->create();
