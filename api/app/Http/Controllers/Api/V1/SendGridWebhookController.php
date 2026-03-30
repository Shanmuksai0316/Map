<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use App\Jobs\ProcessWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SendGridWebhookController extends Controller
{
    /**
     * Handle SendGrid webhook events
     * 
     * Events: delivered, bounce, dropped, spam_report, unsubscribe, etc.
     * Docs: https://docs.sendgrid.com/for-developers/tracking-events/event
     */
    public function handle(Request $request): JsonResponse
    {
        $events = $request->all();
        
        if (empty($events) || !is_array($events)) {
            Log::warning('Invalid SendGrid webhook payload', ['payload' => $events]);
            return response()->json(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $processedCount = 0;
        $skippedCount = 0;

        foreach ($events as $event) {
            $eventId = $event['sg_event_id'] ?? $event['sg_message_id'] ?? null;
            $eventType = $event['event'] ?? 'unknown';
            
            if (!$eventId) {
                Log::warning('SendGrid event missing event_id', ['event' => $event]);
                $skippedCount++;
                continue;
            }

            // Check for duplicate
            if (WebhookLog::eventExists($eventId)) {
                Log::info('Duplicate SendGrid webhook, skipping', [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                ]);
                $skippedCount++;
                continue;
            }

            // Verify signature (optional but recommended)
            $validSignature = $this->verifyWebhookSignature($request, $event);

            // Log webhook
            $log = WebhookLog::logWebhook(
                source: 'sendgrid',
                eventType: $eventType,
                eventId: $eventId,
                validSignature: $validSignature,
                payload: $event
            );

            // Queue processing with retry/backoff
            ProcessWebhookLog::dispatch($log->id);
            $processedCount++;
        }

        Log::info('SendGrid webhooks processed', [
            'processed' => $processedCount,
            'skipped' => $skippedCount,
        ]);

        return response()->json([
            'status' => 'success',
            'processed' => $processedCount,
            'skipped' => $skippedCount,
        ]);
    }

    /**
     * Verify SendGrid webhook signature
     * 
     * SendGrid uses ECDSA signature verification
     * Docs: https://docs.sendgrid.com/for-developers/tracking-events/getting-started-event-webhook-security-features
     */
    private function verifyWebhookSignature(Request $request, array $event): bool
    {
        $verificationKey = config('services.sendgrid.webhook_verification_key');
        
        if (!$verificationKey) {
            Log::warning('[SendGrid] Webhook verification key not configured');
            return false;
        }

        $signature = $request->header('X-Twilio-Email-Event-Webhook-Signature');
        $timestamp = $request->header('X-Twilio-Email-Event-Webhook-Timestamp');
        
        if (!$signature || !$timestamp) {
            Log::warning('[SendGrid] Webhook signature headers missing');
            return false;
        }

        try {
            // Construct signed payload
            $payload = $timestamp . json_encode($event);
            
            // Verify ECDSA signature
            $publicKey = openssl_pkey_get_public($verificationKey);
            if (!$publicKey) {
                Log::error('[SendGrid] Invalid public key');
                return false;
            }

            $verified = openssl_verify(
                $payload,
                base64_decode($signature),
                $publicKey,
                OPENSSL_ALGO_SHA256
            );

            openssl_free_key($publicKey);

            return $verified === 1;
        } catch (\Exception $e) {
            Log::error('[SendGrid] Signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // Processing is delegated to queued job with retry/backoff
}

