<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SmsService
{
    public function send(?string $recipient, string $message, ?string $tenantId, string $template, array $payload = []): bool
    {
        if (!$recipient) {
            Log::warning('sms.send.missing_recipient', compact('template', 'tenantId'));
            return false;
        }

        // Deduplication: Prevent sending same SMS twice within 10 seconds
        // MSG91 discards duplicate requests within 10 seconds
        $dedupeKey = 'sms_send_' . md5($recipient . $message . $template);
        if (Cache::has($dedupeKey)) {
            Log::info('sms.send.duplicate_prevented', [
                'recipient' => substr($recipient, -4),
                'template' => $template,
                'message_preview' => mb_substr($message, 0, 50),
                'note' => 'Duplicate send prevented (same request within 10 seconds)',
            ]);
            return true; // Return true to avoid retry loops, but don't actually send
        }

        // Mark as sent for 12 seconds (slightly longer than MSG91's 10-second window)
        Cache::put($dedupeKey, true, 12);

        $success = $this->dispatch($recipient, $message, $template);

        DB::table('notification_logs')->insert([
            'tenant_id' => $tenantId,
            'recipient' => $recipient,
            'channel' => 'sms',
            'template' => $template,
            'payload_json' => json_encode($payload),
            'status' => $success ? 'sent' : 'failed',
            'sent_at' => $success ? now() : null,
            'related_type' => $payload['related_type'] ?? null,
            'related_id' => $payload['related_id'] ?? null,
            'created_at' => now(),
        ]);

        return $success;
    }

    private function dispatch(string $recipient, string $message, string $template): bool
    {
        Log::info('sms.dispatch', ['recipient' => substr($recipient, -4), 'template' => $template]);

        // Try STPL first if enabled
        if (config('services.stpl.enabled')) {
            $stplResult = $this->dispatchStpl($recipient, $message, $template);
            // If STPL returns a boolean result (true/false), use it
            if ($stplResult !== null) {
                return $stplResult;
            }
            // If STPL is enabled but returns null (not configured), fallback to MSG91
        }

        // Fallback to MSG91
        $msg91Result = $this->dispatchMsg91($recipient, $message, $template);
        return $msg91Result;
    }

    private function dispatchStpl(string $recipient, string $message, string $template): ?bool
    {
        $enabled = (bool) config('services.stpl.enabled');
        $apiKey = config('services.stpl.api_key');
        $senderId = config('services.stpl.sender_id', 'MAPHMS');
        $route = config('services.stpl.route', '4');
        $templateId = config("services.stpl.templates.{$template}");

        if (!$enabled || !$apiKey) {
            return null; // Not configured, try fallback
        }

        // If template ID is not configured, use template name as ID
        // STPL uses template names (like 'approval_rejected_sick_leave') as template identifiers
        if (!$templateId) {
            $templateId = $template;
            Log::info('sms.stpl.using_template_name_as_id', [
                'template' => $template,
                'template_id' => $templateId,
                'recipient' => substr($recipient, -4),
                'note' => 'Using template name as template ID (STPL pattern)',
            ]);
        }

        try {
            // STPL API endpoint - using common Indian SMS provider pattern
            // Most providers use template_id with variables in message body
            $payload = [
                'to' => $recipient,
                'sender' => $senderId,
                'route' => $route,
                'template_id' => $templateId,
                'message' => $message, // Message with variables already replaced
            ];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://api.stpl.com/v1/sms/send', $payload);

            if ($response->failed()) {
                Log::error('sms.stpl.send.failed', [
                    'template' => $template,
                    'template_id' => $templateId,
                    'recipient' => substr($recipient, -4),
                    'response_body' => $response->body(),
                    'response_status' => $response->status(),
                ]);
                return false;
            }

            $responseData = $response->json();
            
            // Check response format - common patterns for success
            if (isset($responseData['status']) && in_array(strtolower($responseData['status']), ['success', 'sent', 'queued'])) {
                return true;
            }

            if (isset($responseData['error']) || isset($responseData['message'])) {
                Log::error('sms.stpl.send.error', [
                    'template' => $template,
                    'template_id' => $templateId,
                    'recipient' => substr($recipient, -4),
                    'error' => $responseData['error'] ?? $responseData['message'] ?? 'Unknown error',
                ]);
                return false;
            }

            // If response is successful but format is unexpected, assume success
            return $response->successful();

        } catch (\Throwable $e) {
            Log::error('sms.stpl.send.exception', [
                'template' => $template,
                'template_id' => $templateId,
                'recipient' => substr($recipient, -4),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function dispatchMsg91(string $recipient, string $message, string $template): bool
    {
        $enabled = (bool) config('services.msg91.enabled');
        $key = config('services.msg91.key');
        $sender = config('services.msg91.sender_id', 'MAPHMS');
        $templateId = config("services.msg91.templates.{$template}");

        if (!$enabled || !$key) {
            Log::warning('sms.noop', [
                'to' => substr($recipient, -4),
                'message' => mb_substr($message, 0, 120),
                'reason' => 'MSG91 disabled or no key',
            ]);
            return false;
        }

        try {
            // For DLT templates, MSG91 requires specific format
            // The message must match the approved template exactly
            if ($templateId) {
                // DLT Template format: Template ID goes in the SMS array item
                $payload = [
                    'sender' => $sender,
                    'route' => '4',
                    'country' => '91',
                    // Compatibility: include DLT ID at top-level as well for providers/gateways
                    // that read template id from root instead of sms item.
                    'DLT_TE_ID' => $templateId,
                    'template_id' => $templateId,
                    'sms' => [
                        [
                            'message'   => $message,
                            'to'        => [$recipient],
                            'DLT_TE_ID' => $templateId, // DLT Template ID inside SMS array
                            'template_id' => $templateId,
                        ],
                    ],
                ];
            } else {
                // Non-DLT format
                $payload = [
                    'sender' => $sender,
                    'route' => '4',
                    'country' => '91',
                    'sms' => [
                        [
                            'message' => $message,
                            'to' => [$recipient],
                        ],
                    ],
                ];
            }

            // Log the exact payload we are sending to MSG91 (for debugging)
            Log::info('sms.msg91.payload', [
                'template'    => $template,
                'template_id' => $templateId,
                'recipient'   => substr($recipient, -4),
            ]);

            // For OTP login, use MSG91's sendhttp-style payload to mirror successful DLT requests
            // seen in provider logs (country/encrypt/unicode/mobiles/route/sender/DLT_TE_ID).
            if ($template === 'otp_login') {
                $legacyPayload = [
                    'authkey' => $key,
                    'sender' => $sender,
                    'route' => '4',
                    'country' => '91',
                    'DLT_TE_ID' => $templateId,
                    'message' => $message,
                    'mobiles' => ltrim($recipient, '+'),
                    'unicode' => '1',
                    'encrypt' => '0',
                ];

                $response = Http::asForm()->post('https://api.msg91.com/api/sendhttp.php', $legacyPayload);
            } else {
                $response = Http::withHeaders([
                    'accept' => 'application/json',
                    'authkey' => $key,
                    'content-type' => 'application/json',
                ])->post('https://api.msg91.com/api/v2/sendsms', $payload);
            }

            $responseData = $response->json();
            $responseBody = $response->body();
            
            // Log full response for debugging (use info level so it's always logged)
            Log::info('sms.msg91.api_response', [
                'template' => $template,
                'template_id' => $templateId,
                'recipient' => substr($recipient, -4),
                'http_status' => $response->status(),
            ]);

            if ($response->failed()) {
                Log::error('sms.msg91.send.failed', [
                    'template' => $template,
                    'template_id' => $templateId,
                    'recipient' => substr($recipient, -4),
                    'response_body' => $responseBody,
                    'response_status' => $response->status(),
                ]);
                return false;
            }
            
            // Check for MSG91 error response (even if HTTP status is 200)
            // MSG91 can return HTTP 200 but with error in response body
            $hasError = false;
            $errorMessage = null;
            
            if (isset($responseData['type']) && $responseData['type'] === 'error') {
                $hasError = true;
                $errorMessage = $responseData['message'] ?? 'Unknown error';
            } elseif (isset($responseData['message']) && stripos($responseData['message'], 'error') !== false) {
                $hasError = true;
                $errorMessage = $responseData['message'];
            } elseif (isset($responseData['error']) || isset($responseData['errors'])) {
                $hasError = true;
                $errorMessage = $responseData['error'] ?? json_encode($responseData['errors'] ?? []);
            }
            
            if ($hasError) {
                Log::error('sms.msg91.send.error', [
                    'template' => $template,
                    'template_id' => $templateId,
                    'recipient' => substr($recipient, -4),
                    'error' => $errorMessage,
                ]);
                
                // If DLT template error, log it specifically
                if (stripos($errorMessage, 'template') !== false || stripos($errorMessage, 'dlt') !== false || stripos($errorMessage, '211') !== false) {
                    Log::warning('sms.msg91.dlt_template_error', [
                        'template_id' => $templateId,
                        'error' => $errorMessage,
                        'note' => 'DLT template error detected. Check template ID and message format match exactly.',
                    ]);
                }
                
                return false;
            }

            // Check if response indicates success
            // MSG91 v2 API returns different success indicators
            $isSuccess = false;
            if (isset($responseData['type']) && $responseData['type'] === 'success') {
                $isSuccess = true;
            } elseif (isset($responseData['request_id']) || isset($responseData['message']) && stripos($responseData['message'], 'success') !== false) {
                $isSuccess = true;
            } elseif ($response->successful() && !$hasError) {
                $isSuccess = true;
            }

            if (!$isSuccess) {
                Log::warning('sms.msg91.uncertain_status', [
                    'template' => $template,
                    'template_id' => $templateId,
                    'recipient' => substr($recipient, -4),
                    'http_status' => $response->status(),
                    'note' => 'Response does not clearly indicate success or failure',
                ]);
            }

            return $isSuccess;
        } catch (\Throwable $e) {
            Log::error('sms.msg91.send.exception', [
                'template' => $template,
                'template_id' => $templateId ?? null,
                'recipient' => substr($recipient, -4),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

}
