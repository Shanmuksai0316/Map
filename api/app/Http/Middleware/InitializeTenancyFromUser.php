<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stancl\Tenancy\Facades\Tenancy;

/**
 * Initialize tenancy based on the authenticated user's tenant_id
 * Used for Rector panel which is accessed via central domain
 */
class InitializeTenancyFromUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        if ($user && $user->tenant_id) {
            try {
                $tenant = \Stancl\Tenancy\Database\Models\Tenant::find($user->tenant_id);
                
                if ($tenant) {
                    Tenancy::initialize($tenant);
                }
            } catch (\Throwable $e) {
                // Log error but continue - tenancy initialization failed
                \Log::warning('Failed to initialize tenancy from user', [
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $next($request);
    }
}

