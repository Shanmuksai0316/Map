<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitSuperAdmin
{
    public function __construct(private RateLimiter $limiter)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = $request->route()?->getName() ?: 'admin';
        $path = $request->path();
        $isLogin = str_contains($path, 'admin/login') || str_contains($routeName, 'filament.admin.auth');

        $maxAttempts = $isLogin ? 20 : 120;
        $decaySeconds = $isLogin ? 300 : 60; // 5 minutes for login, 1 minute for others

        $key = sprintf('sa:%s:%s', $user?->id ?? $request->ip(), $isLogin ? 'login' : $routeName);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            abort(429, 'Too many admin requests. Please slow down.');
        }

        $this->limiter->hit($key, $decaySeconds);

        return $next($request);
    }
}

