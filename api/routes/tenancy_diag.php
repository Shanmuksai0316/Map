<?php

/*
|--------------------------------------------------------------------------
| Tenancy Diagnostic Routes
|--------------------------------------------------------------------------
|
| These routes are ONLY active when ENABLE_TENANCY_DIAG=true
| Used for debugging tenant resolution, DNS configuration, and session state.
|
| SECURITY: These routes expose system internals. Only enable in staging/dev.
| NEVER enable in production unless actively debugging an issue.
|
*/

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

if (env('ENABLE_TENANCY_DIAG', false)) {
    
    Route::prefix('tenancy')->group(function () {
        
        /**
         * WHO AM I - Tenant & User Identity
         * 
         * Returns current tenant context, user identity, roles, and guard.
         * Helps debug tenant initialization and user access issues.
         */
        Route::get('/_whoami', function (Request $request) {
            $tenant = tenant();
            $auth = auth(); // @phpstan-ignore-line
            $user = $auth->user(); // @phpstan-ignore-line
            
            return response()->json([
                'tenant' => [
                    'initialized' => tenancy()->initialized,
                    'id' => $tenant?->id,
                    'code' => $tenant?->code,
                    'name' => $tenant?->name,
                    'status' => $tenant?->status?->value,
                    'domain' => $request->getHost(),
                    'database' => $tenant ? config('database.connections.tenant.database') : null,
                ],
                'user' => [
                    'authenticated' => $auth->check(), // @phpstan-ignore-line
                    'id' => $user?->id,
                    'name' => $user?->name,
                    'email' => $user?->email,
                    'tenant_id' => $user?->tenant_id,
                    'kind' => $user?->kind,
                    'roles' => $user?->roles?->pluck('name')->toArray() ?? [],
                    'guard' => $auth->getDefaultDriver(), // @phpstan-ignore-line
                ],
                'request' => [
                    'host' => $request->getHost(),
                    'url' => $request->fullUrl(),
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                ],
                'timestamp' => now()->toIso8601String(),
            ]);
        });
        
        /**
         * DNS & HEADERS - Network Configuration
         * 
         * Returns request host, forwarded headers (ALB/CloudFront), and detected tenant.
         * Helps debug DNS configuration, proxy headers, and subdomain routing.
         */
        Route::get('/_dns', function (Request $request) {
            $tenant = null;
            $tenantDomain = null;
            
            // Try to find tenant by current domain
            try {
                $domain = \Stancl\Tenancy\Database\Models\Domain::where('domain', $request->getHost())->first();
                if ($domain) {
                    $tenant = $domain->tenant;
                    $tenantDomain = $domain->domain;
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
            
            return response()->json([
                'request' => [
                    'host' => $request->getHost(),
                    'http_host' => $request->server('HTTP_HOST'),
                    'server_name' => $request->server('SERVER_NAME'),
                    'url' => $request->url(),
                    'full_url' => $request->fullUrl(),
                    'scheme' => $request->getScheme(),
                    'port' => $request->getPort(),
                ],
                'headers' => [
                    'x_forwarded_for' => $request->header('X-Forwarded-For'),
                    'x_forwarded_proto' => $request->header('X-Forwarded-Proto'),
                    'x_forwarded_host' => $request->header('X-Forwarded-Host'),
                    'x_forwarded_port' => $request->header('X-Forwarded-Port'),
                    'x_real_ip' => $request->header('X-Real-IP'),
                    'host' => $request->header('Host'),
                ],
                'tenant_resolution' => [
                    'detected_tenant_id' => $tenant?->id,
                    'detected_tenant_code' => $tenant?->code,
                    'detected_tenant_name' => $tenant?->name,
                    'domain_in_db' => $tenantDomain,
                    'tenancy_initialized' => tenancy()->initialized,
                    'current_tenant_id' => tenant()?->id,
                    'current_tenant_code' => tenant()?->code,
                ],
                'config' => [
                    'central_domains' => config('tenancy.central_domains'),
                    'app_url' => config('app.url'),
                    'session_domain' => config('session.domain'),
                    'sanctum_stateful' => config('sanctum.stateful'),
                ],
                'timestamp' => now()->toIso8601String(),
            ]);
        });
        
        /**
         * SESSION INFO - Session & Cookie State
         * 
         * Returns session configuration and cookie settings.
         * Helps debug session persistence and cross-subdomain issues.
         */
        Route::get('/_session', function (Request $request) {
            $auth = auth(); // @phpstan-ignore-line
            
            return response()->json([
                'session' => [
                    'driver' => config('session.driver'),
                    'domain' => config('session.domain'),
                    'secure' => config('session.secure'),
                    'same_site' => config('session.same_site'),
                    'http_only' => config('session.http_only'),
                    'lifetime' => config('session.lifetime'),
                    'session_id' => session()->getId(),
                    'has_session' => session()->isStarted(),
                ],
                'cookies' => [
                    'all_cookies' => $request->cookies->all(),
                    'cookie_names' => array_keys($request->cookies->all()),
                ],
                'auth' => [
                    'authenticated' => $auth->check(), // @phpstan-ignore-line
                    'user_id' => $auth->id(), // @phpstan-ignore-line
                    'guard' => $auth->getDefaultDriver(), // @phpstan-ignore-line
                ],
                'timestamp' => now()->toIso8601String(),
            ]);
        })->middleware('web');
        
    });
}

