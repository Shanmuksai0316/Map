<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SkipRouteForAdminDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        
        // Check if this is the admin domain
        $isAdminDomain = in_array($host, ['admin.mapservices.in', 'admin.localhost']) || 
                         (app()->environment('local') && str_contains($host, 'admin'));
        
        // If admin domain, skip this route and let Filament handle it
        if ($isAdminDomain && $request->is('/')) {
            abort(404); // Let Filament handle it
        }
        
        return $next($request);
    }
}

