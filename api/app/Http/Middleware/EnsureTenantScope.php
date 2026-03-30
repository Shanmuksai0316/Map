<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class EnsureTenantScope
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('tenancy.enabled', true)) {
            return $next($request);
        }

        // For mobile API routes, use user's tenant_id or X-Tenant-Code header
        $isMobileRoute = $request->is('api/v1/mobile/*');
        
        if ($isMobileRoute) {
            $tenantId = null;
            
            // Log authentication status for debugging
            \Log::info('EnsureTenantScope: Mobile route detected', [
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'has_user' => $request->user() !== null,
                'user_id' => $request->user()?->id,
                'has_auth_header' => $request->hasHeader('Authorization'),
                'auth_header_preview' => $request->hasHeader('Authorization') ? substr($request->header('Authorization'), 0, 20) . '...' : 'missing',
                'tenant_code_header' => $request->header('X-Tenant-Code'),
            ]);
            
            // If user is authenticated, use their tenant_id
            if ($request->user()) {
                $user = $request->user();
                $tenantId = $user->tenant_id;
                
                \Log::info('EnsureTenantScope: User authenticated', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                    'user_roles' => $user->roles->pluck('name')->toArray(),
                ]);
                
                if (!$tenantId) {
                    // Super Admin doesn't need tenant
                    if ($user->hasRole('Super Admin')) {
                        return $next($request);
                    }
                    \Log::warning('EnsureTenantScope: User has no tenant assigned', [
                        'user_id' => $user->id,
                        'user_roles' => $user->roles->pluck('name')->toArray(),
                    ]);
                    return response()->json(['error' => 'User has no tenant assigned'], 400);
                }
            } else {
                // For unauthenticated mobile routes (like OTP verification), use X-Tenant-Code header
                $tenantCode = $request->header('X-Tenant-Code');
                if ($tenantCode) {
                    $tenant = \App\Models\Tenant::where('code', $tenantCode)->first();
                    if ($tenant) {
                        $tenantId = $tenant->id;
                        \Log::info('EnsureTenantScope: Using tenant from header', [
                            'tenant_code' => $tenantCode,
                            'tenant_id' => $tenantId,
                        ]);
                    } else {
                        \Log::warning('EnsureTenantScope: Tenant code not found', [
                            'tenant_code' => $tenantCode,
                        ]);
                    }
                } else {
                    \Log::warning('EnsureTenantScope: No tenant code header and user not authenticated', [
                        'url' => $request->fullUrl(),
                    ]);
                }
            }
            
            // If we have a tenant ID, set it in the session
            if ($tenantId) {
                // Load tenant model
                $tenant = \App\Models\Tenant::find($tenantId);
                if (!$tenant) {
                    \Log::error('EnsureTenantScope: Tenant ID found but model not found', [
                        'tenant_id' => $tenantId,
                    ]);
                    return response()->json(['error' => 'Tenant not found'], 404);
                }
                
                // Set PostgreSQL session variable for mobile routes
                DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);
                
                try {
                    return $next($request);
                } finally {
                    // IMPORTANT:
                    // Clearing custom GUCs via `SET ... = NULL` is invalid in Postgres.
                    // Use RESET, and swallow errors so we don't mask the real response.
                    try {
                        DB::statement("RESET app.current_tenant_id");
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }
            
            // If no tenant found and it's an auth route, allow it (tenant will be determined from user lookup)
            if ($request->is('api/v1/mobile/auth/*')) {
                \Log::info('EnsureTenantScope: Allowing auth route without tenant');
                return $next($request);
            }
            
            // For authenticated routes, if user is not authenticated, Sanctum will handle it
            // But if user IS authenticated but has no tenant, we need to return an error
            if ($request->user()) {
                // User is authenticated but has no tenant_id (and is not Super Admin)
                \Log::error('EnsureTenantScope: Authenticated user has no tenant_id', [
                    'url' => $request->fullUrl(),
                    'user_id' => $request->user()->id,
                    'user_roles' => $request->user()->roles->pluck('name')->toArray(),
                ]);
                
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/tenant_required',
                    'title' => 'Tenant Required',
                    'status' => 400,
                    'detail' => 'Your account is not associated with any institution. Please contact support.',
                ], 400);
            }
            
            // User is not authenticated - return 401
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthenticated',
                'title' => 'Unauthenticated',
                'status' => 401,
                'detail' => 'Authentication required. Please log in again.',
            ], 401);
        }

        // For regular routes, require tenancy initialization
        if (!tenancy()->initialized) {
            return response()->json(['error' => 'Tenant not initialized'], 400);
        }

        $tenant = tenant();
        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        if ($request->user()) {
            $user = $request->user();
            if (!$user->hasRole('Super Admin') && $user->tenant_id !== $tenant->id) {
                return response()->json(['error' => 'Access denied to this tenant'], 403);
            }
        }

        // Set PostgreSQL session variable (use set_config for parameter binding)
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenant->id]);

        try {
        return $next($request);
        } finally {
        // Reset session variable (use RESET; SET ... NULL is invalid)
        try {
            DB::statement("RESET app.current_tenant_id");
        } catch (\Throwable $e) {
            // ignore
        }
        }
    }
}
