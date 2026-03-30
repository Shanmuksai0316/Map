<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

class Authenticate extends Middleware
{
    /**
     * Handle an unauthenticated user.
     * Always throw AuthenticationException and let the exception handler deal with it.
     * This avoids calling route('login') which may not exist.
     */
    protected function unauthenticated($request, array $guards)
    {
        // Check if token is corrupted (Zustand state object or [object Object])
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $tokenValue = substr($authHeader, 7);
            if ($tokenValue === '[object Object]' || 
                (str_starts_with($tokenValue, '{') && (str_contains($tokenValue, '"_h"') || str_contains($tokenValue, '"_i"'))) ||
                str_contains($tokenValue, '[object Object]')) {
                // Token is corrupted - provide helpful error message
                \Log::error('Corrupted token detected in Authenticate middleware', [
                    'url' => $request->fullUrl(),
                    'token_preview' => substr($tokenValue, 0, 50),
                ]);
            }
        }
        
        throw new AuthenticationException('Unauthenticated.', $guards);
    }
    
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     * This method is never called since we override unauthenticated(), but we keep it
     * to satisfy the parent class interface.
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
