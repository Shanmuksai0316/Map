<?php

namespace App\Jobs;

use App\Models\WebhookLog;
use App\Services\Webhooks\SendGridWebhookProcessor;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessWebhookLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $webhookLogId)
    {
    }

    public function handle(): void
    {
        /** @var WebhookLog|null $log */
        $log = WebhookLog::find($this->webhookLogId);

        if (!$log) {
            return;
        }

        // Skip if already processed
        if ($log->status === 'processed') {
            return;
        }

        try {
            match ($log->source) {
                'sendgrid' => app(SendGridWebhookProcessor::class)->process($log->payload ?? []),
                default => Log::info('Webhook source not handled; marking processed', ['source' => $log->source]),
            };

            $log->update([
                'status' => 'processed',
                'processed_at' => now(),
                'attempts' => $log->attempts + 1,
                'last_error' => null,
                'next_retry_at' => null,
            ]);
        } catch (Exception $e) {
            $attempts = $log->attempts + 1;
            $backoffSeconds = min(3600, 2 ** $attempts * 60); // capped exponential backoff (max 1h)

            $log->update([
                'status' => 'failed',
                'attempts' => $attempts,
                'last_error' => $e->getMessage(),
                'next_retry_at' => Carbon::now()->addSeconds($backoffSeconds),
            ]);

            // Re-dispatch for retry if attempts remain
            if ($attempts < $this->tries) {
                self::dispatch($log->id)->delay($backoffSeconds);
            }

            Log::error('Webhook processing failed', [
                'webhook_log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
