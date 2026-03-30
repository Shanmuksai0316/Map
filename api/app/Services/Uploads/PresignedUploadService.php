<?php

namespace App\Services\Uploads;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PresignedUploadService
{
    public function generatePresignedUrl(string $directory, string $filename, string $mimeType, int $maxSize = 5242880): array
    {
        if (config('filesystems.default') !== 's3') {
            return [
                'success' => false,
                'message' => 'File uploads not configured',
            ];
        }

        // Validate file size (5MB default)
        if ($maxSize > 52428800) { // 50MB max
            return [
                'success' => false,
                'message' => 'File size too large',
            ];
        }

        // Validate MIME type
        $allowedTypes = [
            'image/png',
            'image/jpeg',
            'image/jpg',
            'image/gif',
            'image/webp',
        ];

        if (!in_array($mimeType, $allowedTypes)) {
            return [
                'success' => false,
                'message' => 'Invalid file type',
            ];
        }

        // Generate unique path
        $path = $directory . '/' . now()->format('Y/m/d/') . Str::random(16) . '_' . $filename;

        try {
            $client = Storage::disk('s3')->getClient();
            $cmd = $client->getCommand('PutObject', [
                'Bucket' => config('filesystems.disks.s3.bucket'),
                'Key' => $path,
                'ContentType' => $mimeType,
                'ACL' => 'private',
            ]);

            $presigned = $client->createPresignedRequest($cmd, '+10 minutes');

            return [
                'success' => true,
                'url' => (string) $presigned->getUri(),
                'key' => $path,
                'expires' => now()->addMinutes(10)->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate upload URL',
            ];
        }
    }

    public function validateUploadedFile(string $key, string $expectedMime): bool
    {
        if (config('filesystems.default') !== 's3') {
            return false;
        }

        try {
            $client = Storage::disk('s3')->getClient();
            $result = $client->headObject([
                'Bucket' => config('filesystems.disks.s3.bucket'),
                'Key' => $key,
            ]);

            return $result['ContentType'] === $expectedMime;
        } catch (\Exception $e) {
            return false;
        }
    }
}
