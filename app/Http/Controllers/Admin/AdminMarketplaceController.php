<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceService;
use App\Models\MarketplaceOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AdminMarketplaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MarketplaceService::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }


        $perPage = $request->input('per_page', 20);
        $services = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $services->items(),
            'current_page' => $services->currentPage(),
            'last_page' => $services->lastPage(),
            'per_page' => $services->perPage(),
            'total' => $services->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:marketplace_services,name',
            'description' => 'required|string',
            'requirements' => 'nullable|array',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        $service = MarketplaceService::create($validated);

        return response()->json($service, 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $service = MarketplaceService::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:marketplace_services,name,' . $id,
            'description' => 'sometimes|string',
            'requirements' => 'nullable|array',
            'price' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $service->update($validated);

        return response()->json($service->fresh());
    }

    public function destroy($id): JsonResponse
    {
        $service = MarketplaceService::findOrFail($id);

        // Check if service has orders
        if ($service->marketplaceOrders()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete marketplace service that has associated orders.'
            ], 422);
        }

        $service->delete();

        return response()->json(['message' => 'Marketplace service deleted successfully']);
    }

    public function toggleStatus($id): JsonResponse
    {
        $service = MarketplaceService::findOrFail($id);
        $service->update(['is_active' => !$service->is_active]);

        return response()->json($service->fresh());
    }

    public function orders(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $perPage = min(max((int) $perPage, 1), 100); // Limit between 1 and 100

        $query = MarketplaceOrder::with([
            'user',
            'company',
            'marketplaceService'
        ]);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by company status if provided
        if ($request->has('company_status')) {
            $query->whereHas('company', function ($q) use ($request) {
                $q->where('status', $request->company_status);
            });
        }

        $orders = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $orders->items(),
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
        ]);
    }

    public function acceptOrder($id): JsonResponse
    {
        $order = MarketplaceOrder::findOrFail($id);

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending orders can be accepted'
            ], 422);
        }

        $order->update([
            'status' => 'provided',
        ]);

        return response()->json([
            'message' => 'Marketplace order accepted successfully',
            'data' => $order->fresh(['user', 'company', 'marketplaceService'])
        ]);
    }

    public function uploadFile(Request $request, $id): JsonResponse
    {
        // Laravel automatically parses 'files[]' as 'files' array
        // Get all files from the request
        $allFiles = $request->allFiles();
        
        // Debug logging
        Log::debug('File upload request', [
            'all_files_keys' => array_keys($allFiles),
            'has_files' => $request->hasFile('files'),
            'has_files_array' => $request->hasFile('files[]'),
            'content_type' => $request->header('Content-Type'),
        ]);
        
        // Try to get files - Laravel parses 'files[]' as 'files'
        $files = null;
        
        // Check for 'files' key (Laravel converts 'files[]' to 'files')
        if (isset($allFiles['files'])) {
            $files = $allFiles['files'];
        }
        // Also check direct file access
        elseif ($request->hasFile('files')) {
            $files = $request->file('files');
        }
        // Check all keys for any file input
        else {
            foreach ($allFiles as $key => $value) {
                if (is_array($value) && !empty($value) && $value[0] instanceof \Illuminate\Http\UploadedFile) {
                    $files = $value;
                    break;
                } elseif ($value instanceof \Illuminate\Http\UploadedFile) {
                    $files = [$value];
                    break;
                }
            }
        }
        
        if (!$files || (is_array($files) && count($files) === 0)) {
            return response()->json([
                'message' => 'No files provided',
                'debug' => [
                    'all_files_keys' => array_keys($allFiles),
                    'has_files' => $request->hasFile('files'),
                    'content_type' => $request->header('Content-Type'),
                ]
            ], 422);
        }

        // Ensure files is an array
        if (!is_array($files)) {
            $files = [$files];
        }

        // Validate each file
        foreach ($files as $file) {
            if (!$file || !$file->isValid()) {
                return response()->json([
                    'message' => 'Invalid file provided'
                ], 422);
            }
            
            if ($file->getSize() > 10240 * 1024) { // 10MB in bytes
                return response()->json([
                    'message' => 'File size exceeds 10MB limit: ' . $file->getClientOriginalName()
                ], 422);
            }
        }

        $order = MarketplaceOrder::findOrFail($id);
        
        $uploadedFiles = [];
        $currentFiles = $order->file ?? [];
        
        foreach ($files as $file) {
            try {
                $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                
                $minioDisk = Storage::disk('minio');
                $minioConfig = config('filesystems.disks.minio');
                
                // Log configuration for debugging
                Log::debug('Attempting MinIO upload', [
                    'endpoint' => $minioConfig['endpoint'] ?? 'not set',
                    'bucket' => $minioConfig['bucket'] ?? 'not set',
                    'file_name' => $fileName,
                ]);
                
                // Upload to MinIO - this will throw an exception if it fails
                try {
                    Log::debug('Calling storeAs for MinIO', [
                        'directory' => 'marketplace-orders/' . $order->id,
                        'file_name' => $fileName,
                    ]);
                    
                    $filePath = $file->storeAs('marketplace-orders/' . $order->id, $fileName, 'minio');
                    
                    Log::debug('storeAs completed', [
                        'file_path' => $filePath,
                    ]);
                } catch (\Exception $storeException) {
                    // Get the actual error from the storage operation
                    $errorMessage = $storeException->getMessage();
                    $errorTrace = $storeException->getTraceAsString();
                    
                    Log::error('MinIO storeAs failed', [
                        'error' => $errorMessage,
                        'error_class' => get_class($storeException),
                        'error_code' => $storeException->getCode(),
                        'error_trace' => substr($errorTrace, 0, 500), // First 500 chars of trace
                        'endpoint' => $minioConfig['endpoint'] ?? 'not set',
                        'bucket' => $minioConfig['bucket'] ?? 'not set',
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                    ]);
                    
                    // Provide a more helpful error message
                    if (strpos($errorMessage, 'bucket') !== false || strpos($errorMessage, 'Bucket') !== false || strpos($errorMessage, 'NoSuchBucket') !== false) {
                        throw new \Exception('Bucket "' . ($minioConfig['bucket'] ?? 'unknown') . '" does not exist or is not accessible. Please create the bucket in MinIO or check permissions.');
                    } elseif (strpos($errorMessage, 'connection') !== false || strpos($errorMessage, 'Connection') !== false || strpos($errorMessage, 'SSL') !== false || strpos($errorMessage, 'certificate') !== false) {
                        throw new \Exception('Cannot connect to MinIO server at ' . ($minioConfig['endpoint'] ?? 'unknown') . '. Check endpoint, SSL settings, and credentials. Error: ' . $errorMessage);
                    } elseif (strpos($errorMessage, 'Access Denied') !== false || strpos($errorMessage, 'InvalidAccessKeyId') !== false || strpos($errorMessage, 'SignatureDoesNotMatch') !== false) {
                        throw new \Exception('MinIO authentication failed. Check your MINIO_ACCESS_KEY and MINIO_SECRET_KEY credentials.');
                    } else {
                        throw new \Exception('MinIO upload failed: ' . $errorMessage);
                    }
                } catch (\Throwable $storeException) {
                    // Catch any other throwable (including errors)
                    $errorMessage = $storeException->getMessage();
                    Log::error('MinIO storeAs failed with Throwable', [
                        'error' => $errorMessage,
                        'error_class' => get_class($storeException),
                        'endpoint' => $minioConfig['endpoint'] ?? 'not set',
                        'bucket' => $minioConfig['bucket'] ?? 'not set',
                    ]);
                    throw new \Exception('MinIO upload failed: ' . $errorMessage);
                }
                
                if (!$filePath) {
                    // Get more details about why storage failed
                    Log::error('MinIO storage failed - file path is null', [
                        'order_id' => $order->id,
                        'file_name' => $file->getClientOriginalName(),
                        'minio_endpoint' => $minioConfig['endpoint'] ?? 'not set',
                        'minio_bucket' => $minioConfig['bucket'] ?? 'not set',
                    ]);
                    throw new \Exception('Failed to store file: ' . $file->getClientOriginalName() . '. Check MinIO connection and bucket configuration.');
                }
                
                // Verify file was actually stored (optional check - might fail on connection issues)
                try {
                    if (!$minioDisk->exists($filePath)) {
                        Log::warning('File path returned but file does not exist in MinIO', [
                            'file_path' => $filePath,
                        ]);
                        // Don't throw - continue anyway as the upload might have succeeded
                    }
                } catch (\Exception $existsException) {
                    // If exists() check fails, it might be a connection issue
                    Log::warning('Could not verify file existence in MinIO', [
                        'file_path' => $filePath,
                        'error' => $existsException->getMessage(),
                    ]);
                    // Continue anyway - the file might be there but we can't verify
                }
                
                // Get public URL from MinIO
                $fileUrl = Storage::disk('minio')->url($filePath);
                
                $uploadedFiles[] = [
                    'file_path' => $filePath,
                    'file_name' => $file->getClientOriginalName(),
                    'file_url' => $fileUrl,
                    'uploaded_at' => now()->toISOString(),
                ];
                
                Log::info('File uploaded successfully to MinIO', [
                    'file_path' => $filePath,
                    'file_name' => $file->getClientOriginalName(),
                ]);
            } catch (\Exception $e) {
                // Log full exception details
                Log::error('File upload error', [
                    'order_id' => $order->id,
                    'file_name' => $file->getClientOriginalName(),
                    'error_message' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'minio_config' => [
                        'endpoint' => config('filesystems.disks.minio.endpoint'),
                        'bucket' => config('filesystems.disks.minio.bucket'),
                        'key' => config('filesystems.disks.minio.key') ? 'set' : 'not set',
                    ]
                ]);
                
                return response()->json([
                    'message' => 'Failed to upload file: ' . $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'details' => 'Check Laravel logs for more information. Verify MinIO connection and bucket exists.'
                ], 500);
            }
        }
        
        // Merge with existing files
        $allFiles = array_merge($currentFiles, $uploadedFiles);
        
        // Update marketplace order with all file paths
        $order->update(['file' => $allFiles]);
        
        // Log success
        Log::info('Files uploaded successfully', [
            'order_id' => $order->id,
            'files_count' => count($uploadedFiles),
            'total_files' => count($allFiles),
        ]);

        return response()->json([
            'message' => 'Files uploaded successfully',
            'files' => $uploadedFiles,
            'total_files' => count($allFiles),
            'success' => true,
        ], 200);
    }

    public function getFiles($id): JsonResponse
    {
        $order = MarketplaceOrder::findOrFail($id);
        
        $files = [];
        
        if ($order->file && is_array($order->file)) {
            foreach ($order->file as $fileData) {
                // Handle both old format (string) and new format (array)
                if (is_string($fileData)) {
                    $fileUrl = Storage::disk('minio')->url($fileData);
                    $files[] = [
                        'file_path' => $fileData,
                        'file_name' => basename($fileData),
                        'file_url' => $fileUrl,
                    ];
                } else {
                    // New format with metadata
                    $fileUrl = Storage::disk('minio')->url($fileData['file_path']);
                    $files[] = [
                        'file_path' => $fileData['file_path'],
                        'file_name' => $fileData['file_name'] ?? basename($fileData['file_path']),
                        'file_url' => $fileUrl,
                        'uploaded_at' => $fileData['uploaded_at'] ?? null,
                    ];
                }
            }
        }
        
        return response()->json([
            'data' => $files,
        ]);
    }

    public function deleteFile(Request $request, $id, $fileIndex): JsonResponse
    {
        $order = MarketplaceOrder::findOrFail($id);
        
        $files = $order->file ?? [];
        
        if (isset($files[$fileIndex])) {
            $fileToDelete = $files[$fileIndex];
            $filePath = is_string($fileToDelete) ? $fileToDelete : $fileToDelete['file_path'];
            
            // Delete from MinIO
            Storage::disk('minio')->delete($filePath);
            
            // Remove from array
            unset($files[$fileIndex]);
            $files = array_values($files); // Re-index array
            
            $order->update(['file' => $files]);
            
            return response()->json(['message' => 'File deleted successfully']);
        }
        
        return response()->json(['message' => 'File not found'], 404);
    }

    public function deleteOrder($id): JsonResponse
    {
        $order = MarketplaceOrder::findOrFail($id);
        
        // Delete associated files from MinIO if they exist
        if ($order->file && is_array($order->file)) {
            foreach ($order->file as $fileData) {
                $filePath = is_string($fileData) ? $fileData : ($fileData['file_path'] ?? null);
                if ($filePath) {
                    try {
                        Storage::disk('minio')->delete($filePath);
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete file from MinIO', [
                            'file_path' => $filePath,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
        
        $order->delete();
        
        return response()->json(['message' => 'Marketplace order deleted successfully']);
    }
}
