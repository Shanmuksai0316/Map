<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validate Tenant Access Middleware
 * 
 * Ensures that the request is made within the context of a valid tenant
 * and that the user has access to that tenant.
 */
class ValidateTenantAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip validation for central routes
        if ($this->isCentralRoute($request)) {
            return $next($request);
        }

        // Check if tenancy is initialized
        if (!tenancy()->initialized) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/tenant_not_initialized',
                'title' => 'Tenant Not Initialized',
                'status' => 400,
                'detail' => 'Request must be made within a tenant context.',
            ], 400);
        }

        $tenant = tenant();
        if (!$tenant) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/tenant_not_found',
                'title' => 'Tenant Not Found',
                'status' => 404,
                'detail' => 'The requested tenant could not be found.',
            ], 404);
        }

        // Check if tenant is accessible
        if (!$tenant->canAccess()) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/tenant_suspended',
                'title' => 'Tenant Suspended',
                'status' => 403,
                'detail' => 'This tenant is currently suspended or archived.',
                'tenant_status' => $tenant->status->value,
            ], 403);
        }

        // For authenticated requests, verify user belongs to this tenant
        if ($request->user()) {
            $user = $request->user();
            
            // Super Admins can access any tenant
            if ($user->hasRole('Super Admin')) {
                return $next($request);
            }

            // Regular users must belong to this tenant
            // Safety check: ensure user has tenant_id (should always be set now)
            if (empty($user->tenant_id) || $user->tenant_id !== $tenant->id) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/tenant_access_denied',
                    'title' => 'Access Denied',
                    'status' => 403,
                    'detail' => 'You do not have access to this tenant.',
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * Check if the request is for a central route
     */
    private function isCentralRoute(Request $request): bool
    {
        // Note: $request->path() returns paths WITHOUT leading slashes
        // e.g., 'admin' not '/admin', 'api/v1/tenants' not '/api/v1/tenants'
        $centralPaths = [
            'api/v1/tenants',
            'api/v1/healthz',
            'admin',
            'healthz',
        ];

        $path = $request->path();
        
        foreach ($centralPaths as $centralPath) {
            if (str_starts_with($path, $centralPath)) {
                return true;
            }
        }

        return false;
    }
}
