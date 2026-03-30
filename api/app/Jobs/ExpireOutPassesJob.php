<?php

namespace App\Jobs;

use App\Services\OutPassExpiryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireOutPassesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function tags(): array
    {
        return ['outpass', 'expiry'];
    }

    public function __construct() {}

    public function handle(): void
    {
        try {
            $expiryService = app(OutPassExpiryService::class);
            $expiredCount = $expiryService->expireOutPasses();
            
            if ($expiredCount > 0) {
                Log::info("Expired {$expiredCount} outpasses", [
                    'expired_count' => $expiredCount,
                    'timestamp' => now()->toISOString(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to expire outpasses', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Re-throw to mark job as failed
            throw $e;
        }
    }
}
