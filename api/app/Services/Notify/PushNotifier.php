<?php

namespace App\Services\Notify;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PushNotifier
{
    private ?string $accessToken = null;

    public function enabled(): bool
    {
        if (!config('services.fcm.enabled')) {
            return false;
        }
        
        // Check for V1 API (service account) or Legacy API (server key)
        $hasServiceAccount = config('services.fcm.service_account_json') || config('services.fcm.service_account_path');
        $hasServerKey = config('services.fcm.server_key');
        
        return $hasServiceAccount || $hasServerKey;
    }
    
    /**
     * Check if using V1 API (service account) or Legacy API (server key)
     */
    private function usingV1Api(): bool
    {
        return (bool)(config('services.fcm.service_account_json') || config('services.fcm.service_account_path'));
    }

    /**
     * Get OAuth2 access token for FCM V1 API
     */
    private function getAccessToken(): string
    {
        // Cache token for 50 minutes (tokens expire in 1 hour)
        return Cache::remember('fcm_access_token', 50 * 60, function () {
            $serviceAccount = $this->getServiceAccount();
            
            if (!$serviceAccount) {
                throw new \RuntimeException('FCM service account not configured');
            }

            $jwt = $this->createJWT($serviceAccount);
            $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($tokenResponse->failed()) {
                Log::error('FCM OAuth2 token request failed', [
                    'status' => $tokenResponse->status(),
                    'body' => $tokenResponse->body(),
                ]);
                throw new \RuntimeException('Failed to get FCM access token');
            }

            return $tokenResponse->json('access_token');
        });
    }

    /**
     * Get service account credentials
     */
    private function getServiceAccount(): ?array
    {
        // Try JSON string first
        $jsonString = config('services.fcm.service_account_json');
        if ($jsonString) {
            $decoded = json_decode($jsonString, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Normalize private key: replace literal \n with actual newlines
                if (isset($decoded['private_key'])) {
                    $decoded['private_key'] = str_replace(['\\n', '\n'], "\n", $decoded['private_key']);
                }
                return $decoded;
            }
        }

        // Try file path
        $filePath = config('services.fcm.service_account_path');
        if ($filePath && file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Normalize private key: replace literal \n with actual newlines
                if (isset($decoded['private_key'])) {
                    $decoded['private_key'] = str_replace(['\\n', '\n'], "\n", $decoded['private_key']);
                }
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Create JWT for OAuth2 assertion
     */
    private function createJWT(array $serviceAccount): string
    {
        $now = time();
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        $signatureInput = $headerEncoded . '.' . $payloadEncoded;

        $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
        if (!$privateKey) {
            throw new \RuntimeException('Invalid FCM service account private key');
        }

        openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($privateKey);

        $signatureEncoded = $this->base64UrlEncode($signature);
        return $signatureInput . '.' . $signatureEncoded;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Send notification to specific FCM token using V1 API or Legacy API
     */
    public function toToken(string $token, string $title, string $body, array $data = []): void
    {
        if (!$this->enabled()) { 
            Log::info('FCM no-op'); 
            return; 
        }

        // Use Legacy API if service account not configured
        if (!$this->usingV1Api()) {
            $this->toTokenLegacy($token, $title, $body, $data);
            return;
        }

        $projectId = config('services.fcm.project_id');
        $accessToken = $this->getAccessToken();

        // FCM V1 API payload structure
        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map('strval', $data), // FCM requires string values
                'android' => [
                    'priority' => 'high',
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

        if ($response->failed()) {
            Log::error('FCM V1 send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'token' => substr($token, 0, 20) . '...',
            ]);
            $response->throw();
        }

        Log::debug('FCM V1 notification sent', [
            'message_id' => $response->json('name'),
        ]);
    }

    public function toUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = DB::table('push_device_tokens')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('is_active')->orWhere('is_active', true);
            })
            ->distinct()
            ->pluck('token');

        foreach ($tokens as $t) {
            try {
                $this->toToken($t, $title, $body, $data);
            } catch (\Exception $e) {
                $error = strtolower($e->getMessage());
                if (
                    str_contains($error, 'registration-token-not-registered')
                    || str_contains($error, 'invalid registration token')
                    || str_contains($error, 'invalid argument')
                    || str_contains($error, 'notregistered')
                ) {
                    DB::table('push_device_tokens')
                        ->where('token', $t)
                        ->update([
                            'is_active' => false,
                            'updated_at' => now(),
                        ]);
                }

                Log::error('Failed to send FCM to user token', [
                    'user_id' => $userId,
                    'token' => substr($t, 0, 20) . '...',
                    'error' => $e->getMessage(),
                ]);
                // Continue with other tokens
            }
        }

        // Also persist an in-app notification row so it appears under the bell icon.
        try {
            $user = User::find($userId);
            if ($user) {
                DB::table('user_notifications')->insert([
                    'user_id'    => $user->id,
                    'tenant_id'  => $user->tenant_id,
                    'title'      => $title,
                    'message'    => $body,
                    'type'       => isset($data['type']) ? (string) $data['type'] : 'general',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to insert user_notifications row', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Convenience: send to a user using a named template from config/push_templates.php.
     *
     * @param int   $userId   Recipient user ID
     * @param string $template Template key (e.g. 'rector.outpass_submitted')
     * @param array $vars      Placeholder variables for title/body (['student_name' => '...'])
     * @param array $data      Extra FCM data payload (screen, ids, etc.)
     */
    public function toUserTemplate(int $userId, string $template, array $vars = [], array $data = []): void
    {
        $templates = config('push_templates', []);
        if (! isset($templates[$template])) {
            Log::warning('push.template_missing', [
                'template' => $template,
                'user_id' => $userId,
            ]);
            return;
        }

        $def = $templates[$template];
        $title = $this->renderTemplate($def['title'] ?? '', $vars);
        $body = $this->renderTemplate($def['body'] ?? '', $vars);

        $this->toUser($userId, $title, $body, $data);
    }

    /**
     * Simple placeholder replacement for {placeholders} in title/body templates.
     */
    private function renderTemplate(string $template, array $vars): string
    {
        if ($template === '' || empty($vars)) {
            return $template;
        }

        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }

    /**
     * Send to topic using V1 API or Legacy API
     */
    public function toTopic(string $topic, string $title, string $body, array $data = []): void
    {
        if (!$this->enabled()) { 
            Log::info('FCM no-op'); 
            return; 
        }

        // Use Legacy API if service account not configured
        if (!$this->usingV1Api()) {
            $this->toTopicLegacy($topic, $title, $body, $data);
            return;
        }

        $projectId = config('services.fcm.project_id');
        $accessToken = $this->getAccessToken();

        $payload = [
            'message' => [
                'topic' => $topic,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map('strval', $data),
                'android' => [
                    'priority' => 'high',
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

        if ($response->failed()) {
            Log::error('FCM V1 topic send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'topic' => $topic,
            ]);
            $response->throw();
        }
    }

    /**
     * Send notification using Legacy FCM API (fallback when service account keys are blocked)
     */
    private function toTokenLegacy(string $token, string $title, string $body, array $data = []): void
    {
        $serverKey = config('services.fcm.server_key');
        
        if (empty($serverKey)) {
            Log::error('FCM Legacy API: Server key not configured');
            throw new \RuntimeException('FCM Server Key is required for Legacy API');
        }

        $payload = [
            'to' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => array_map('strval', $data),
        ];

        $response = Http::withHeaders([
            'Authorization' => 'key=' . $serverKey,
            'Content-Type' => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', $payload);

        if ($response->failed()) {
            Log::error('FCM Legacy API send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'token' => substr($token, 0, 20) . '...',
            ]);
            $response->throw();
        }

        Log::debug('FCM Legacy API notification sent', [
            'message_id' => $response->json('message_id'),
        ]);
    }

    /**
     * Send to topic using Legacy API (fallback)
     */
    private function toTopicLegacy(string $topic, string $title, string $body, array $data = []): void
    {
        $serverKey = config('services.fcm.server_key');
        
        if (empty($serverKey)) {
            Log::error('FCM Legacy API: Server key not configured');
            throw new \RuntimeException('FCM Server Key is required for Legacy API');
        }

        $payload = [
            'to' => '/topics/' . $topic,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => array_map('strval', $data),
        ];

        $response = Http::withHeaders([
            'Authorization' => 'key=' . $serverKey,
            'Content-Type' => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', $payload);

        if ($response->failed()) {
            Log::error('FCM Legacy API topic send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'topic' => $topic,
            ]);
            $response->throw();
        }
    }
}
