<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check Tenant Status Middleware
 * 
 * Blocks suspended or archived tenants from accessing the system.
 * Super Admins (without tenant_id) are not affected by this check.
 */
class CheckTenantStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Skip check for unauthenticated users or Super Admins (no tenant_id)
        if (!$user || !$user->tenant_id) {
            return $next($request);
        }
        
        // Check tenant status
        $tenant = Tenant::find($user->tenant_id);
        
        if (!$tenant || !$tenant->canAccess()) {
            // Log out the user immediately
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            // Return appropriate error based on request type
            if ($request->expectsJson()) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/tenant_suspended',
                    'title' => 'Access Suspended',
                    'status' => 403,
                    'detail' => 'Your institution\'s access has been suspended. Please contact support.',
                    'tenant_status' => $tenant?->status ?? 'unknown',
                ], 403);
            }
            
            // For web requests, redirect to login with error message
            return redirect()
                ->route('filament.admin.auth.login')
                ->with('error', 'Your institution\'s access has been suspended. Please contact support.');
        }
        
        return $next($request);
    }
}

