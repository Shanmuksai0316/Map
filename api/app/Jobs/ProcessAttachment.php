<?php

namespace App\Jobs;

use App\Models\Attachment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessAttachment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Attachment $attachment
    ) {}

    public function handle(): void
    {
        $disk = Storage::disk('public');
        try {
            if (!$disk->exists($this->attachment->key)) {
                throw new \RuntimeException('Attachment file not found: ' . $this->attachment->key);
            }
            $tempPath = tempnam(sys_get_temp_dir(), 'attachment_');
            $content = $disk->get($this->attachment->key);
            file_put_contents($tempPath, $content);

            $isClean = $this->processFile($tempPath);

            if ($isClean) {
                $this->attachment->update([
                    'status' => 'clean',
                    'metadata' => array_merge($this->attachment->metadata ?? [], [
                        'processed_at' => now()->toISOString(),
                        'scan_result' => 'clean',
                    ]),
                ]);

                Log::info('Attachment processed successfully', [
                    'attachment_id' => $this->attachment->id,
                    'filename' => $this->attachment->filename,
                ]);
            } else {
                $this->attachment->update([
                    'status' => 'quarantined',
                    'metadata' => array_merge($this->attachment->metadata ?? [], [
                        'processed_at' => now()->toISOString(),
                        'scan_result' => 'quarantined',
                        'reason' => 'Failed security scan',
                    ]),
                ]);

                $quarantineKey = 'quarantine/' . $this->attachment->key;
                $disk->move($this->attachment->key, $quarantineKey);
                $this->attachment->update(['key' => $quarantineKey]);

                Log::warning('Attachment quarantined', [
                    'attachment_id' => $this->attachment->id,
                    'filename' => $this->attachment->filename,
                ]);
            }

            unlink($tempPath);

        } catch (\Exception $e) {
            Log::error('Attachment processing failed', [
                'attachment_id' => $this->attachment->id,
                'error' => $e->getMessage(),
            ]);

            $this->attachment->update([
                'status' => 'failed',
                'metadata' => array_merge($this->attachment->metadata ?? [], [
                    'processed_at' => now()->toISOString(),
                    'scan_result' => 'failed',
                    'error' => $e->getMessage(),
                ]),
            ]);

            throw $e;
        }
    }

    private function processFile(string $filePath): bool
    {
        // Basic file validation
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            return false;
        }

        // Check file size
        if (filesize($filePath) > 10 * 1024 * 1024) { // 10MB
            return false;
        }

        // Basic MIME type validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
        ];

        if (!in_array($mimeType, $allowedMimes)) {
            return false;
        }

        // TODO: Implement actual antivirus scan
        // For now, just do basic checks
        if (strpos($mimeType, 'image/') === 0) {
            return $this->processImage($filePath);
        }

        return true;
    }

    private function processImage(string $filePath): bool
    {
        try {
            // Basic image validation
            $imageInfo = getimagesize($filePath);
            if (!$imageInfo) {
                return false;
            }

            // TODO: Strip EXIF data for privacy
            // This is a placeholder - implement actual EXIF stripping
            
            return true;
        } catch (\Exception $e) {
            Log::warning('Image processing failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}



