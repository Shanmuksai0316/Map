<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;

class AuditPiiAccess
{
    public function __construct(private AuditService $auditService)
    {
    }

    public function handle(Request $request, Closure $next, ?string $action = null)
    {
        $response = $next($request);

        // Sanitize query parameters to exclude sensitive data
        $queryParams = $this->sanitizeQueryParams($request->query());

        $this->auditService->log($action ?? 'pii_access', null, [
            'path' => $request->path(),
            'method' => $request->method(),
            'status' => $response->getStatusCode(),
            'query' => $queryParams,
        ]);

        return $response;
    }

    /**
     * Sanitize query parameters by removing sensitive fields
     */
    private function sanitizeQueryParams(array $query): array
    {
        $sensitiveKeys = [
            'token', 'api_key', 'api_token', 'access_token', 'refresh_token',
            'password', 'password_confirmation', 'secret', 'key',
            'auth', 'authorization', 'credential', 'credentials',
            'otp', 'otp_code', 'verification_code',
            'ssn', 'social_security_number', 'credit_card', 'cvv',
        ];

        $sanitized = [];
        foreach ($query as $key => $value) {
            $keyLower = strtolower($key);
            
            // Check if key contains sensitive terms
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (str_contains($keyLower, $sensitiveKey)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                // Recursively sanitize nested arrays
                $sanitized[$key] = is_array($value) ? $this->sanitizeQueryParams($value) : $value;
            }
        }

        return $sanitized;
    }
}
