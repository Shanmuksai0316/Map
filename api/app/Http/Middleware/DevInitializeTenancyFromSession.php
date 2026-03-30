<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DevInitializeTenancyFromSession
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('local') && class_exists('Stancl\\Tenancy\\Facades\\Tenancy')) {
            // Ensure session is started before accessing it
            $tenantKey = null;
            if (method_exists($request, 'hasSession') && $request->hasSession()) {
                try {
                    $tenantKey = $request->session()->get('dev_tenant_id');
                } catch (\Throwable $e) {
                    // ignore; session not available yet
                }
            }

            if (! $tenantKey) {
                $headerTenant = $request->header('X-Tenant-Code') ?: $request->header('X-Tenant-Id');
                if ($headerTenant) {
                    $tenantKey = $headerTenant;
                    if ($request->hasSession()) {
                        $request->session()->put('dev_tenant_id', $tenantKey);
                    }
                }
            }

            // For campus-manager panel in local, default to STXAV if no tenant is set
            if (! $tenantKey && str_contains($request->path(), 'campus-manager')) {
                $tenantKey = 'STXAV';
                if ($request->hasSession()) {
                    $request->session()->put('dev_tenant_id', $tenantKey);
                }
            }

            if ($tenantKey) {
                $tenant = \Stancl\Tenancy\Database\Models\Tenant::query()->find($tenantKey)
                    ?: \Stancl\Tenancy\Database\Models\Tenant::query()
                        ->where('code', $tenantKey)
                        ->orWhere('data->code', $tenantKey)
                        ->orWhere('data->slug', $tenantKey)
                        ->first();
                if ($tenant) {
                    \Stancl\Tenancy\Facades\Tenancy::initialize($tenant);
                }
            }
        }

        return $next($request);
    }
}


