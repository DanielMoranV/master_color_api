<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class FileUploadService
{
    private const ALLOWED_IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB in bytes

    public function uploadImage(UploadedFile $file, string $folder = 'images', string $prefix = 'img'): string
    {
        $this->validateImage($file);
        
        $fileName = $this->generateFileName($file, $prefix);
        $path = $file->storeAs($folder, $fileName, 'public');
        
        return $path;
    }

    public function deleteImage(?string $imagePath): void
    {
        if (!$imagePath) {
            return;
        }

        if (str_starts_with($imagePath, 'http')) {
            $parsedUrl = parse_url($imagePath);
            if (isset($parsedUrl['path'])) {
                $path = str_replace('/storage/', '', $parsedUrl['path']);
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
            return;
        }

        if (Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }
    }

    private function validateImage(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (!in_array($extension, self::ALLOWED_IMAGE_TYPES)) {
            throw new InvalidArgumentException(
                'Tipo de archivo no permitido. Solo se permiten: ' . implode(', ', self::ALLOWED_IMAGE_TYPES)
            );
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException(
                'El archivo es demasiado grande. Máximo ' . (self::MAX_FILE_SIZE / 1024 / 1024) . 'MB permitidos'
            );
        }

        if (!$file->isValid()) {
            throw new InvalidArgumentException('El archivo subido no es válido');
        }
    }

    private function generateFileName(UploadedFile $file, string $prefix): string
    {
        $extension = $file->getClientOriginalExtension();
        return $prefix . '_' . uniqid() . '_' . time() . '.' . $extension;
    }

    public function getImageUrl(?string $imagePath): ?string
    {
        if (!$imagePath) {
            return null;
        }

        if (str_starts_with($imagePath, 'http')) {
            return $imagePath;
        }

        return Storage::disk('public')->url($imagePath);
    }
}