<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class AdminMaintenanceController extends Controller
{
    /**
     * Check if maintenance mode is enabled
     */
    public function status(): JsonResponse
    {
        // Laravel uses 'down' file for maintenance mode
        $downFile = storage_path('framework/down');
        $isEnabled = File::exists($downFile);

        return response()->json([
            'enabled' => $isEnabled,
        ]);
    }

    /**
     * Enable maintenance mode
     * Excludes admin routes so admins can still access the system
     */
    public function enable(): JsonResponse
    {
        try {
            // Enable maintenance mode
            Artisan::call('down');
            
            // Manually edit the maintenance file to exclude admin routes
            $downFile = storage_path('framework/down');
            if (File::exists($downFile)) {
                $data = json_decode(File::get($downFile), true);
                
                // Note: Admin routes are bypassed in public/index.php
                // But we still set except as a backup
                $data['except'] = ['api/admin/*'];
                
                // Set a simple template so the except check runs
                if (!isset($data['template'])) {
                    $data['template'] = '<!DOCTYPE html><html><head><title>Service Unavailable</title></head><body><h1>System Under Maintenance</h1><p>We\'ll be back soon!</p></body></html>';
                }
                
                // Write the updated data back
                File::put($downFile, json_encode($data, JSON_PRETTY_PRINT));
            }
            
            return response()->json([
                'message' => 'Maintenance mode enabled successfully',
                'enabled' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to enable maintenance mode: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disable maintenance mode
     */
    public function disable(): JsonResponse
    {
        try {
            Artisan::call('up');
            
            return response()->json([
                'message' => 'Maintenance mode disabled successfully',
                'enabled' => false,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to disable maintenance mode: ' . $e->getMessage(),
            ], 500);
        }
    }
}

