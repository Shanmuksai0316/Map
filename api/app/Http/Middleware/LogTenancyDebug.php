<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogTenancyDebug
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Log request details BEFORE tenant initialization
        Log::info('TENANT_DEBUG: Request received', [
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'method' => $request->method(),
            'host' => $request->getHost(),
            'http_host' => $request->header('Host'),
            'server_name' => $request->server('SERVER_NAME'),
            'forwarded_host' => $request->header('X-Forwarded-Host'),
            'tenant_before' => tenant() ? tenant()->code : null,
        ]);

        $response = $next($request);

        // Log tenant status AFTER middleware chain
        Log::info('TENANT_DEBUG: After middleware', [
            'tenant_after' => tenant() ? tenant()->code : null,
            'tenancy_initialized' => tenancy()->initialized,
        ]);

        return $response;
    }
}
