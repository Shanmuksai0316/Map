<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;

class HmsDiagEnv extends Command
{
    protected $signature = 'hms:diag:env {--web : Include web request data if available}';
    protected $description = 'Diagnose HMS environment configuration for AWS ALB/HTTPS issues';

    public function handle()
    {
        $diag = $this->gatherDiagnostics();
        
        if ($this->option('web') && app()->bound('request')) {
            $diag['web'] = $this->gatherWebDiagnostics();
        }
        
        $this->line(json_encode($diag, JSON_PRETTY_PRINT));
        
        return 0;
    }

    private function gatherDiagnostics(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'environment' => [
                'app_env' => config('app.env'),
                'app_debug' => config('app.debug'),
                'app_url' => config('app.url'),
                'app_name' => config('app.name'),
            ],
            'session' => [
                'driver' => config('session.driver'),
                'domain' => config('session.domain'),
                'secure' => config('session.secure'),
                'same_site' => config('session.same_site'),
                'cookie_name' => config('session.cookie'),
                'path' => config('session.path'),
                'lifetime' => config('session.lifetime'),
                'encrypt' => config('session.encrypt'),
                'http_only' => config('session.http_only'),
            ],
            'cache' => [
                'driver' => config('cache.default'),
                'store' => config('cache.stores.' . config('cache.default')),
            ],
            'queue' => [
                'driver' => config('queue.default'),
                'connection' => config('queue.connections.' . config('queue.default')),
            ],
            'filament' => [
                'panels' => $this->getFilamentPanels(),
            ],
            'auth' => [
                'default_guard' => config('auth.defaults.guard'),
                'web_guard' => config('auth.guards.web'),
            ],
            'proxy' => [
                'trusted_proxies' => config('trustedproxy.proxies'),
                'headers' => config('trustedproxy.headers'),
            ],
            'tenancy' => [
                'active' => class_exists('Stancl\Tenancy\Facades\Tenancy') ? 
                    (method_exists(\Stancl\Tenancy\Facades\Tenancy::class, 'check') ? 
                        \Stancl\Tenancy\Facades\Tenancy::check() : false) : false,
                'current_tenant' => class_exists('Stancl\Tenancy\Facades\Tenancy') ? 
                    (method_exists(\Stancl\Tenancy\Facades\Tenancy::class, 'tenant') ? 
                        \Stancl\Tenancy\Facades\Tenancy::tenant()?->id : null) : null,
            ],
            'storage' => [
                'sessions_writable' => is_writable(storage_path('framework/sessions')),
                'sessions_path' => storage_path('framework/sessions'),
            ],
            'bootstrap_cache' => [
                'config_exists' => file_exists(base_path('bootstrap/cache/config.php')),
                'config_timestamp' => file_exists(base_path('bootstrap/cache/config.php')) ? 
                    filemtime(base_path('bootstrap/cache/config.php')) : null,
                'services_exists' => file_exists(base_path('bootstrap/cache/services.php')),
                'packages_exists' => file_exists(base_path('bootstrap/cache/packages.php')),
            ],
            'env_flags' => [
                'debug_403' => env('DEBUG_403', false),
                'force_https' => env('FORCE_HTTPS', false),
                'session_secure_cookie' => env('SESSION_SECURE_COOKIE'),
                'session_domain' => env('SESSION_DOMAIN'),
                'session_same_site' => env('SESSION_SAME_SITE'),
            ],
        ];
    }

    private function gatherWebDiagnostics(): array
    {
        $request = app('request');
        
        return [
            'current_url' => URL::current(),
            'full_url' => $request->fullUrl(),
            'scheme' => $request->getScheme(),
            'host' => $request->getHost(),
            'port' => $request->getPort(),
            'headers' => $this->maskHeaders($request->headers->all()),
            'user' => Auth::check() ? [
                'id' => Auth::id(),
                'email' => Auth::user()->email,
                'roles' => Auth::user()->roles->pluck('name')->toArray(),
                'can_access_admin' => Auth::user()->canAccessPanel(app(\Filament\Panel::class)->id('admin')),
            ] : null,
        ];
    }

    private function getFilamentPanels(): array
    {
        $panels = [];
        
        try {
            $panelConfigs = config('filament.panels', []);
            foreach ($panelConfigs as $id => $config) {
                $panels[$id] = [
                    'path' => $config['path'] ?? '/',
                    'auth_guard' => $config['auth_guard'] ?? 'web',
                    'middleware' => $config['middleware'] ?? [],
                ];
            }
        } catch (\Exception $e) {
            $panels['error'] = $e->getMessage();
        }
        
        return $panels;
    }

    private function maskHeaders(array $headers): array
    {
        $masked = [];
        $sensitive = ['cookie', 'authorization', 'x-csrf-token', 'x-xsrf-token'];
        
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitive)) {
                $masked[$key] = ['***masked***'];
            } else {
                $masked[$key] = $value;
            }
        }
        
        return $masked;
    }
}
