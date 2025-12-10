<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
// Bypass maintenance mode for admin routes and maintenance status endpoint
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$isAdminRoute = str_starts_with($requestUri, '/api/admin/');
$isMaintenanceStatus = str_starts_with($requestUri, '/api/v1/maintenance/status');

if (!$isAdminRoute && !$isMaintenanceStatus && file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
