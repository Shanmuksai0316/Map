<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class Admin403Tracer
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only run when DEBUG_403 is enabled
        if (!config('app.debug_403', false)) {
            return $next($request);
        }

        // Only trace admin panel requests
        if (!$request->is('admin*')) {
            return $next($request);
        }

        $startTime = microtime(true);
        
        // Capture request details
        $traceData = [
            'timestamp' => now()->toISOString(),
            'request' => [
                'path' => $request->path(),
                'method' => $request->method(),
                'client_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'forwarded_proto' => $request->header('X-Forwarded-Proto'),
                'forwarded_host' => $request->header('X-Forwarded-Host'),
                'host' => $request->getHost(),
                'scheme' => $request->getScheme(),
                'url' => $request->url(),
                'full_url' => $request->fullUrl(),
            ],
            'auth' => [
                'check' => Auth::check(),
                'guard_name' => Auth::guard()->getName(),
                'user_id' => Auth::id(),
                'user_email' => Auth::user()?->email,
                'user_roles' => Auth::user()?->roles?->pluck('name')->toArray() ?? [],
                'user_tenant_id' => Auth::user()?->tenant_id ?? null,
            ],
            'session' => [
                'id' => session()->getId(),
                'driver' => config('session.driver'),
                'domain' => config('session.domain'),
                'secure' => config('session.secure'),
                'same_site' => config('session.same_site'),
                'lifetime' => config('session.lifetime'),
            ],
            'route' => [
                'name' => Route::currentRouteName(),
                'action' => Route::currentRouteAction(),
                'middleware' => Route::current()?->gatherMiddleware() ?? [],
            ],
            'tenancy' => [
                'active' => class_exists('Stancl\Tenancy\Facades\Tenancy') ? 
                    \Stancl\Tenancy\Facades\Tenancy::check() : false,
                'tenant_id' => class_exists('Stancl\Tenancy\Facades\Tenancy') ? 
                    \Stancl\Tenancy\Facades\Tenancy::tenant()?->id : null,
            ],
            'filament' => [
                'panel_id' => null, // Will be set if we can determine it
                'can_access_panel' => null, // Will be set if user exists
            ],
            'config' => [
                'app_url' => config('app.url'),
                'app_env' => config('app.env'),
                'trust_proxies' => config('trustedproxy.proxies'),
                'session_secure_cookie' => config('session.secure'),
            ],
        ];

        // Try to determine Filament panel access
        if (Auth::check()) {
            try {
                $user = Auth::user();
                $panel = app(\Filament\Panel::class);
                $panel->id('admin');
                
                $traceData['filament']['panel_id'] = 'admin';
                $traceData['filament']['can_access_panel'] = $user->canAccessPanel($panel);
            } catch (\Exception $e) {
                $traceData['filament']['error'] = $e->getMessage();
            }
        }

        // Process the request
        $response = $next($request);

        // Add response details
        $traceData['response'] = [
            'status_code' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
        ];

        // If 403/401, try to determine the cause
        if (in_array($response->getStatusCode(), [401, 403])) {
            $traceData['denied_by'] = $this->determineDenialCause($request, $response);
        }

        // Log the trace
        Log::channel('admin403')->info('Admin403Tracer', $traceData);

        return $response;
    }

    private function determineDenialCause(Request $request, Response $response): ?string
    {
        // This is a simplified approach - in practice, you might need more sophisticated detection
        if ($response->getStatusCode() === 401) {
            return 'Authentication required';
        }

        if ($response->getStatusCode() === 403) {
            if (!Auth::check()) {
                return 'Not authenticated';
            }

            // Check if it's a Filament-specific denial
            if ($request->is('admin*')) {
                return 'Filament panel access denied';
            }

            return 'Access forbidden';
        }

        return null;
    }
}



