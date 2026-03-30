<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Throwable;

class DevExposeException
{
    public function handle(Request $request, Closure $next)
    {
        if (!app()->environment('local')) {
            return $next($request);
        }

        try {
            return $next($request);
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $trace = $e->getTraceAsString();
            $body = "DEV EXCEPTION\n\n{$message}\n\n{$trace}";
            return response($body, 500, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'X-Dev-Exception' => substr($message, 0, 200),
            ]);
        }
    }
}


