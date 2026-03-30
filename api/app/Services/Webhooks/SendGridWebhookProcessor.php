<?php

namespace App\Services\Webhooks;

use Illuminate\Support\Facades\Log;

class SendGridWebhookProcessor
{
    public function process(array $event): void
    {
        $eventType = $event['event'] ?? 'unknown';

        match ($eventType) {
            'delivered' => $this->handleDelivered($event),
            'bounce' => $this->handleBounce($event),
            'dropped' => $this->handleDropped($event),
            'spam_report' => $this->handleSpamReport($event),
            'unsubscribe' => $this->handleUnsubscribe($event),
            'open' => $this->handleOpen($event),
            'click' => $this->handleClick($event),
            default => Log::info("SendGrid event type '{$eventType}' not handled", ['event' => $event]),
        };
    }

    private function handleDelivered(array $event): void
    {
        Log::info('[SendGrid] Email delivered', [
            'email' => $event['email'] ?? null,
            'message_id' => $event['sg_message_id'] ?? null,
        ]);
    }

    private function handleBounce(array $event): void
    {
        $email = $event['email'] ?? null;
        $reason = $event['reason'] ?? 'Unknown';

        Log::warning('[SendGrid] Email bounced', [
            'email' => $email,
            'reason' => $reason,
            'type' => $event['type'] ?? 'Unknown',
        ]);
    }

    private function handleDropped(array $event): void
    {
        $email = $event['email'] ?? null;
        $reason = $event['reason'] ?? 'Unknown';

        Log::warning('[SendGrid] Email dropped', [
            'email' => $email,
            'reason' => $reason,
        ]);
    }

    private function handleSpamReport(array $event): void
    {
        $email = $event['email'] ?? null;

        Log::warning('[SendGrid] Email marked as spam', [
            'email' => $email,
        ]);
    }

    private function handleUnsubscribe(array $event): void
    {
        $email = $event['email'] ?? null;

        Log::info('[SendGrid] User unsubscribed', [
            'email' => $email,
        ]);
    }

    private function handleOpen(array $event): void
    {
        Log::debug('[SendGrid] Email opened', [
            'email' => $event['email'] ?? null,
            'user_agent' => $event['useragent'] ?? null,
        ]);
    }

    private function handleClick(array $event): void
    {
        Log::debug('[SendGrid] Email link clicked', [
            'email' => $event['email'] ?? null,
            'url' => $event['url'] ?? null,
        ]);
    }
}
