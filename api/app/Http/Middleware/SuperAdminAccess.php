<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip authentication check for auth routes (login, logout, password reset, etc.)
        if ($request->is('admin/login*') ||
            $request->is('admin/logout*') ||
            $request->is('admin/password*') ||
            $request->routeIs('filament.admin.auth.*')) {
            return $next($request);
        }

        // If user is not authenticated, let Filament's Authenticate middleware handle the redirect
        if (!auth()->check()) {
            return $next($request);
        }

        // User is authenticated - check if they have Super Admin role
        $user = auth()->user();

        if (!$user->hasRole('Super Admin')) {
            abort(403, 'Access denied. Super Admin role required.');
        }

        return $next($request);
    }
}
