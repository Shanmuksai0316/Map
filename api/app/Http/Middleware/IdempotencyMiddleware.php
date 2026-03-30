<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * Handle idempotent requests using database storage (24h TTL)
     */
    public function handle(Request $request, Closure $next)
    {
        // Only for POST/PUT/PATCH on API routes
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');
        if (!$key || !is_string($key) || Str::length($key) > 128) {
            return $next($request); // Key is optional; no-op if missing
        }

        // Hash the key for storage
        $keyHash = hash('sha256', $key);

        // Calculate request fingerprint
        $requestBody = $request->getContent();
        $requestFingerprint = hash('sha256', $request->method() . "\n" . $request->path() . "\n" . $requestBody);

        // Check for existing idempotency key
        $existing = DB::table('idempotency_keys')
            ->where('key_hash', $keyHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            // Check if request fingerprint matches
            if ($existing->request_fingerprint !== $requestFingerprint) {
                Log::warning('Idempotency key reused with different request', [
                    'idempotency_key_hash' => substr($keyHash, 0, 8),
                    'user_id' => $request->user()?->id,
                    'tenant_id' => $request->user()?->tenant_id,
                    'request_id' => $request->header('X-Request-ID'),
                    'method' => $request->method(),
                    'path' => $request->path(),
                ]);
                
                return response()->json([
                    'type' => 'https://api.map-hms/errors/idempotency/mismatch',
                    'title' => 'Idempotency Key Mismatch',
                    'status' => Response::HTTP_CONFLICT,
                    'detail' => 'Idempotency key re-used with different request.',
                    'code' => 'idempotency/mismatch',
                ], Response::HTTP_CONFLICT);
            }
            
            // Return cached response
            Log::info('Idempotency replay', [
                'idempotency_key_hash' => substr($keyHash, 0, 8),
                'user_id' => $request->user()?->id,
                'tenant_id' => $request->user()?->tenant_id,
                'request_id' => $request->header('X-Request-ID'),
                'method' => $request->method(),
                'path' => $request->path(),
            ]);
            
            $responseData = json_decode($existing->response_json, true);
            $statusCode = $existing->status_code ?? Response::HTTP_OK;

            return response()->json($responseData, $statusCode)
                ->header('X-Idempotency-Replayed', 'true')
                ->header('X-Idempotency-Key', $key);
        }

        // Process request
        $response = $next($request);

        // Store response in database (24h TTL)
        $expiresAt = now()->addHours(24);
        
        DB::table('idempotency_keys')->insert([
            'key_hash' => $keyHash,
            'request_fingerprint' => $requestFingerprint,
            'response_json' => json_encode(json_decode($response->getContent(), true)),
            'status_code' => $response->getStatusCode(),
            'first_seen_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        Log::info('Idempotency response stored', [
            'idempotency_key_hash' => substr($keyHash, 0, 8),
            'user_id' => $request->user()?->id,
            'tenant_id' => $request->user()?->tenant_id,
            'request_id' => $request->header('X-Request-ID'),
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'expires_at' => $expiresAt,
        ]);

        return $response->header('X-Idempotency-Key', $key);
    }
}
