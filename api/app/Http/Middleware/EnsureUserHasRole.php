<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();
        if (!$user || !$user->hasRole($role)) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        return $next($request);
    }
}

