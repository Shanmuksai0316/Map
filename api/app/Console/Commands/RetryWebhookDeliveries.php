<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWebhookLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryWebhookDeliveries extends Command
{
    protected $signature = 'webhooks:retry';

    protected $description = 'Retry failed or pending webhook logs with exponential backoff';

    public function handle(): int
    {
        $due = DB::table('webhook_logs')
            ->whereIn('status', ['queued', 'failed'])
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->limit(100)
            ->pluck('id');

        foreach ($due as $id) {
            ProcessWebhookLog::dispatch((int) $id);
        }

        Log::info('Webhook retry dispatch', ['count' => $due->count()]);
        $this->info("Dispatched {$due->count()} webhook log(s) for retry.");

        return self::SUCCESS;
    }
}
