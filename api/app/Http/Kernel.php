<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\RedirectAdminRootToLogin::class,  // Redirect admin root FIRST - before routing
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\SentryContext::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used instead of class names to conveniently assign middleware to routes and groups.
     *
     * NOTE: For older Laravel versions, route middleware aliases are read from
     * $routeMiddleware instead of $middlewareAliases. To be compatible with both,
     * we define both properties with the same mappings.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

        // Spatie Permission middleware aliases
        'role' => \Spatie\Permission\Middlewares\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middlewares\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middlewares\RoleOrPermissionMiddleware::class,

        // App-specific middleware
        'legacy.softkill' => \App\Http\Middleware\LegacySoftKill::class,
        'tenant.validate' => \App\Http\Middleware\ValidateTenantAccess::class,
        'audit.pii' => \App\Http\Middleware\AuditPiiAccess::class,
        'throttle.superadmin' => \App\Http\Middleware\RateLimitSuperAdmin::class,
        'ensure.campus_manager' => \App\Http\Middleware\EnsureCampusManager::class,
    ];

    /**
     * Route middleware aliases (backwards compatibility).
     *
     * Laravel 9/10 primarily use $middlewareAliases, but older versions and some
     * internals still look at $routeMiddleware. We mirror the aliases here so
     * that 'role', 'permission', etc. are always resolvable.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

        // Spatie Permission middleware aliases
        'role' => \Spatie\Permission\Middlewares\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middlewares\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middlewares\RoleOrPermissionMiddleware::class,

        // App-specific middleware
        'legacy.softkill' => \App\Http\Middleware\LegacySoftKill::class,
        'tenant.validate' => \App\Http\Middleware\ValidateTenantAccess::class,
        'audit.pii' => \App\Http\Middleware\AuditPiiAccess::class,
        'throttle.superadmin' => \App\Http\Middleware\RateLimitSuperAdmin::class,
        'ensure.campus_manager' => \App\Http\Middleware\EnsureCampusManager::class,
    ];

    /**
     * Configure the rate limiters for the application.
     */
    /**
     * Bootstrap the application's services.
     */
    public function boot(): void
    {
        parent::boot();
        
        // Add Admin403Tracer middleware when DEBUG_403 is enabled
        if (env('DEBUG_403')) {
            $this->middlewareGroups['web'][] = \App\Http\Middleware\Admin403Tracer::class;
        }
    }

    protected function configureRateLimiting(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            // Exclude mobile auth routes from strict rate limiting during testing
            // Use unlimited rate for mobile auth to prevent 429 errors during development
            if ($request->is('api/v1/mobile/auth/*')) {
                return \Illuminate\Cache\RateLimiting\Limit::none();
            }
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Login rate limiting: 5 attempts per 15 minutes per phone/IP
        \Illuminate\Support\Facades\RateLimiter::for('login', function (\Illuminate\Http\Request $request) {
            $key = $request->input('phone') ?: $request->ip();
            return \Illuminate\Cache\RateLimiting\Limit::perMinutes(15, 5)
                ->by($key)
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again in 15 minutes.',
                        'error' => 'rate_limit_exceeded',
                    ], 429);
                });
        });

        // OTP send rate limiting: 1 per minute, 5 per hour per IP
        \Illuminate\Support\Facades\RateLimiter::for('otp-send', function (\Illuminate\Http\Request $request) {
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(1)->by($request->ip()),
                \Illuminate\Cache\RateLimiting\Limit::perHour(5)->by($request->ip()),
            ];
        });

        // OTP verify rate limiting: 5 per minute per IP
        \Illuminate\Support\Facades\RateLimiter::for('otp-verify', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip());
        });
    }
}
