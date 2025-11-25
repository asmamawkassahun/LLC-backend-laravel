<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    public function storeDocument(UploadedFile $file, string $directory = 'documents'): array
    {
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs($directory, $fileName, 'public');
        
        return [
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }
    
    public function validateDocument(UploadedFile $file, array $allowedTypes = [], int $maxSize = 5242880): bool
    {
        if (!empty($allowedTypes) && !in_array($file->getMimeType(), $allowedTypes)) {
            return false;
        }
        
        if ($file->getSize() > $maxSize) {
            return false;
        }
        
        return true;
    }
    
    public function deleteDocument(string $filePath): bool
    {
        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->delete($filePath);
        }
        
        return false;
    }
}

