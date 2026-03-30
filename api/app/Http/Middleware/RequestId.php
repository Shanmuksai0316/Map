<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate or get existing request ID
        $requestId = $request->header('X-Request-Id') ?: Str::uuid()->toString();

        // Add request ID to request
        $request->headers->set('X-Request-Id', $requestId);

        // Add to log context
        Log::withContext(['request_id' => $requestId]);

        // Process the request
        $response = $next($request);

        // Add request ID to response headers
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
