<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PropagateRequestId
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get request ID from headers or generate one
        $requestId = $request->header('X-Request-Id');

        if ($requestId) {
            // Set request ID in Sentry scope for error tracking
            if (class_exists('\Sentry\configureScope')) {
                \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($requestId): void {
                    $scope->setTag('request_id', $requestId);
                });
            }

            // Set request ID in queue context for job tracking
            if (class_exists('\Illuminate\Support\Facades\Queue') &&
                !\Illuminate\Support\Facades\Queue::getFacadeRoot() instanceof \Illuminate\Support\Testing\Fakes\QueueFake) {
                \Illuminate\Support\Facades\Queue::createPayloadUsing(function () use ($requestId) {
                    return ['request_id' => $requestId];
                });
            }
        }

        return $next($request);
    }
}
