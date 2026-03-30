<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Admin403DebugServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Only register when DEBUG_403 is enabled
        if (!config('app.debug_403', false)) {
            return;
        }

        // Register Gate event listener
        Gate::after(function ($user, $ability, $result, $arguments) {
            Log::channel('admin403')->info('GateEvaluated', [
                'timestamp' => now()->toISOString(),
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'ability' => $ability,
                'result' => $result,
                'arguments' => $arguments,
            ]);
        });
    }

    public function boot(): void
    {
        // Only boot when DEBUG_403 is enabled
        if (!config('app.debug_403', false)) {
            return;
        }

        // Register diagnostic routes
        Route::middleware(['auth:web'])->group(function () {
            Route::get('/__diag/admin', function () {
                // Check if user can view diagnostics
                if (!Gate::allows('viewDiag')) {
                    abort(403, 'Access denied to diagnostics');
                }

                $user = Auth::user();
                
                return response()->json([
                    'timestamp' => now()->toISOString(),
                    'environment' => [
                        'app_url' => config('app.url'),
                        'app_env' => config('app.env'),
                        'app_debug' => config('app.debug'),
                        'url_full' => url()->full(),
                        'request_scheme_host' => request()->getSchemeAndHttpHost(),
                    ],
                    'session' => [
                        'driver' => config('session.driver'),
                        'domain' => config('session.domain'),
                        'secure' => config('session.secure'),
                        'same_site' => config('session.same_site'),
                        'lifetime' => config('session.lifetime'),
                        'encrypt' => config('session.encrypt'),
                        'path' => config('session.path'),
                        'http_only' => config('session.http_only'),
                    ],
                    'filament' => [
                        'auth_guard' => config('filament.auth.guard'),
                        'panels' => array_keys(config('filament.panels', [])),
                    ],
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'roles' => $user->roles->pluck('name')->toArray(),
                        'tenant_id' => $user->tenant_id,
                        'kind' => $user->kind,
                    ],
                    'panel_access' => [
                        'can_access_admin' => $user->canAccessPanel(app(\Filament\Panel::class)->id('admin')),
                    ],
                    'tenancy' => [
                        'active' => class_exists('Stancl\Tenancy\Facades\Tenancy') ? 
                            \Stancl\Tenancy\Facades\Tenancy::check() : false,
                        'tenant_id' => class_exists('Stancl\Tenancy\Facades\Tenancy') ? 
                            \Stancl\Tenancy\Facades\Tenancy::tenant()?->id : null,
                        'current_domain' => class_exists('Stancl\Tenancy\Facades\Tenancy') ? 
                            \Stancl\Tenancy\Facades\Tenancy::tenant()?->domains?->first()?->domain : null,
                    ],
                    'trusted_proxies' => [
                        'proxies' => config('trustedproxy.proxies'),
                        'headers' => config('trustedproxy.headers'),
                        'is_from_trusted_proxy' => method_exists(request(), 'isFromTrustedProxy') ? 
                            request()->isFromTrustedProxy() : 'method_not_available',
                    ],
                    'features' => [
                        'super_admin_staff_mgmt' => config('features.super_admin_staff_mgmt'),
                        'super_admin_reports' => config('features.super_admin_reports'),
                    ],
                    'sanctum' => [
                        'stateful_domains' => config('sanctum.stateful'),
                        'guard' => config('sanctum.guard'),
                    ],
                ], 200, [], JSON_PRETTY_PRINT);
            });
        });
    }
}



