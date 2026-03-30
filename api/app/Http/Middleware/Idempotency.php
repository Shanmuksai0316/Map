<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class Idempotency
{
    public function handle(Request $request, Closure $next)
    {
        // Skip Livewire requests
        if ($request->hasHeader('X-Livewire') || $request->is('livewire/*') || $request->is('livewire') ||
            str_contains($request->path(), 'livewire') || $request->routeIs('livewire.*')) {
            return $next($request);
        }

        if (! $request->isMethod('post') && ! $request->isMethod('put') && ! $request->isMethod('patch')) {
            return $next($request);
        }

        $key = $request->header('X-Idempotency-Key');
        if (! $key) return $next($request);

        // Only apply to authenticated requests to avoid issues with tenant_id access
        $user = auth()->user();
        if (!$user) return $next($request);

        $tenant = (string)$user->tenant_id ?: 'public';
        $cacheKey = "idem:{$tenant}:".sha1($key.':'.$request->fullUrl());

        if ($cached = Cache::get($cacheKey)) {
            return response($cached['body'], $cached['status'], $cached['headers'])
                ->header('X-Idempotency-Replayed', '1');
        }

        $response = $next($request);
        Cache::put($cacheKey, [
            'status' => $response->getStatusCode(),
            'headers'=> $response->headers->all(),
            'body'   => $response->getContent(),
        ], now()->addMinutes(15));

        return $response->header('X-Idempotency-Replayed', '0');
    }
}
