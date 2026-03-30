<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RedirectAdminRootToLogin
{
    /**
     * Handle an incoming request.
     * Redirects admin domain root to /admin/login
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get host - check all possible sources
        $host = $request->getHost() ?: $request->header('Host') ?: ($_SERVER['HTTP_HOST'] ?? '');
        $path = $request->getPathInfo();
        $method = $request->method();
        
        // Log every request for debugging
        Log::info('RedirectAdminRootToLogin - Request', [
            'host' => $host,
            'path' => $path,
            'method' => $method,
            'uri' => $request->getRequestUri(),
        ]);
        
        // CHECK: Only redirect admin domain ROOT path (/) to /admin/login
        // Do NOT redirect other admin paths like /admin/login, /admin/dashboard, etc.
        $hostLower = strtolower(trim($host));
        $isAdminDomain = str_contains($hostLower, 'admin.mapservices.in');
        $isRootPath = ($path === '/' || $path === '');

        Log::info('RedirectAdminRootToLogin - Decision', [
            'isAdminDomain' => $isAdminDomain,
            'isRootPath' => $isRootPath,
            'currentPath' => $path,
            'shouldRedirect' => $isAdminDomain && $isRootPath,
        ]);

        // ONLY redirect admin domain root path - let Filament handle /admin/* routes
        if ($isAdminDomain && $isRootPath && strtoupper($method) === 'GET') {
            Log::info('RedirectAdminRootToLogin - REDIRECTING root to /admin/login');
            return redirect('/admin/login', 302);
        }
        
        return $next($request);
    }
}

