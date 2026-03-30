<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCampusManager
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user has the Campus Manager role.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        \Log::info('EnsureCampusManager middleware', [
            'has_user' => $user !== null,
            'user_id' => $user?->id,
            'user_roles' => $user ? $user->roles->pluck('name')->toArray() : [],
            'has_campus_manager_role' => $user ? $user->hasRole('Campus Manager') : false,
            'path' => $request->path(),
        ]);

        // Allow Campus Manager and Rector (Rector has higher authority)
        $hasAccess = $user && ($user->hasRole('Campus Manager') || $user->hasRole('Rector'));
        
        if (! $hasAccess) {
            \Log::warning('EnsureCampusManager: Access denied', [
                'user_id' => $user?->id,
                'user_roles' => $user ? $user->roles->pluck('name')->toArray() : [],
                'path' => $request->path(),
            ]);
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}

