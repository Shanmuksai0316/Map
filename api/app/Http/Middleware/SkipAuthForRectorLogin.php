<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom Authentication Middleware for Rector Panel
 *
 * Allows rector login and authenticated rector users to bypass tenancy restrictions
 */
class SkipAuthForRectorLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = ltrim($request->path(), '/');

        // 1) Always allow the Rector login + related assets
        if ($this->isRectorLoginRequest($request)) {
            return $next($request);
        }

        // 2) Allow already‑authenticated Rector (or Super Admin) users
        if ($this->isAuthenticatedRectorUser($request)) {
            return $next($request);
        }

        // 3) If this is any Rector panel route and the user is not authenticated,
        //    redirect them to the Rector login page instead of returning 403.
        if (str_starts_with($path, 'rector')) {
            return redirect()->to('/rector/login');
        }

        // For other requests, continue with normal authentication
        return app(\Filament\Http\Middleware\Authenticate::class)->handle($request, $next);
    }

    protected function isRectorLoginRequest(Request $request): bool
    {
        // Allow rector login page and Filament/Livewire asset endpoints
        $path = ltrim($request->path(), '/');
        $host = $request->getHost();

        // Special‑case Skyline rector host, but keep the logic generic
        if (str_contains($host, 'skyline2025.mapservices.in')) {
            // Login page and associated assets under /rector/*
            return str_starts_with($path, 'rector/login')
                || str_starts_with($path, 'rector/livewire')
                || str_starts_with($path, 'rector/filament');
        }

        return str_starts_with($path, 'rector/login')
            || str_starts_with($path, 'rector/livewire')
            || str_starts_with($path, 'rector/filament');
    }

    protected function isAuthenticatedRectorUser(Request $request): bool
    {
        // Check if user is authenticated and has rector role
        // For tenant-based rector access, also check tenant match
        if (auth()->check()) {
            $user = auth()->user();
            if (!$user || (!$user->hasRole('Rector') && !$user->hasRole('Super Admin'))) {
                return false;
            }

            // If user has a tenant_id, ensure they're accessing their own tenant
            if ($user->tenant_id) {
                $currentDomain = $request->getHost();
                $tenant = \App\Models\Tenant::where('domain', $currentDomain)->first();
                if ($tenant && $tenant->id !== $user->tenant_id) {
                    return false; // User trying to access different tenant
                }
            }

            return true;
        }
        return false;
    }
}

