<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the request URL matches APP_URL for Livewire upload so hasValidSignature() passes.
 * When behind a proxy, request()->url() may be http://internal-host/... but the signed URL
 * was generated with APP_URL (e.g. https://admin.mapservices.in). We force scheme and host
 * from config('app.url') so the signature validates.
 */
class ForceHttpsForLivewireUpload
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! str_contains($request->path(), 'livewire/upload-file')) {
            return $next($request);
        }

        $appUrl = config('app.url');
        if (! $appUrl) {
            return $next($request);
        }

        $parsed = parse_url($appUrl);
        if (isset($parsed['scheme']) && $parsed['scheme'] === 'https' && ! $request->secure()) {
            $request->server->set('HTTPS', 'on');
        }
        if (isset($parsed['host'])) {
            $request->server->set('HTTP_HOST', $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : ''));
        }

        return $next($request);
    }
}
