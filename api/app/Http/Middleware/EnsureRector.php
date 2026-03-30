<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRector
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        \Log::info('EnsureRector middleware', [
            'url' => $request->fullUrl(),
            'user_id' => $user?->id,
            'has_user' => !is_null($user),
            'user_roles' => $user?->roles->pluck('name')->toArray() ?? [],
            'has_rector_role' => $user?->hasRole('Rector') ?? false,
        ]);
        
        if (!$user) {
            \Log::warning('EnsureRector middleware - Unauthenticated', [
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'has_auth_header' => $request->hasHeader('Authorization'),
                'auth_header_preview' => $request->hasHeader('Authorization') ? substr($request->header('Authorization'), 0, 20) . '...' : 'missing',
                'tenant_code' => $request->header('X-Tenant-Code'),
            ]);
            
            // Return properly formatted error for mobile API
            if ($request->is('api/v1/mobile/*') && $request->expectsJson()) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/unauthenticated',
                    'title' => 'Unauthenticated',
                    'status' => 401,
                    'detail' => 'Authentication required. Please log in again.',
                ], 401);
            }
            
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        
        // Check if user has Rector role
        if (!$user->hasRole('Rector')) {
            \Log::warning('EnsureRector middleware - Access denied', [
                'user_id' => $user->id,
                'user_roles' => $user->roles->pluck('name')->toArray(),
            ]);
            
            // Return properly formatted error for mobile API
            if ($request->is('api/v1/mobile/*') && $request->expectsJson()) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/forbidden',
                    'title' => 'Forbidden',
                    'status' => 403,
                    'detail' => 'Rector access required.',
                ], 403);
            }
            
            return response()->json(['message' => 'Forbidden. Rector access required.'], 403);
        }
        
        return $next($request);
    }
}
