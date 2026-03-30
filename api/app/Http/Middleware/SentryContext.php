<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\State\Scope;

class SentryContext
{
    public function handle(Request $request, Closure $next)
    {
        if (class_exists('\Sentry\State\Hub')) {
            \Sentry\configureScope(function (Scope $scope) use ($request) {
                $user = auth()->user();

                if ($user) {
                    $scope->setUser([
                        'id' => (string) $user->id,
                        'email' => $user->email,
                        'username' => $user->name,
                        'tenant_id' => $user->tenant_id,
                        'roles' => $user->roles?->pluck('name')->all(),
                    ], true);
                }

                $scope->setContext('request', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                if (session()->has('impersonating_from')) {
                    $scope->setTag('impersonation', 'true');
                    $scope->setExtra('impersonating_from', session('impersonating_from'));
                    $scope->setExtra('impersonated_tenant', session('impersonated_tenant_name'));
                }

                if ($user && $user->tenant_id) {
                    $scope->setTag('tenant_id', (string) $user->tenant_id);
                }
            });
        }

        return $next($request);
    }
}

