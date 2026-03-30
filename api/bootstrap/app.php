<?php

use App\Providers\AuthServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Load health check routes
            require __DIR__.'/../routes/health.php';
            
            // Load diagnostic routes when DEBUG_403 is enabled
            if (env('DEBUG_403', false)) {
                require __DIR__.'/../routes/diag.php';
            }
            
            // Load tenancy diagnostic routes when ENABLE_TENANCY_DIAG is enabled
            if (env('ENABLE_TENANCY_DIAG', false)) {
                require __DIR__.'/../routes/tenancy_diag.php';
            }

            // Staff checklist summary – fallback when reverse proxy strips "api" prefix (path becomes v1/... not api/v1/...)
            Route::middleware(['api', 'auth:sanctum', \App\Http\Middleware\EnsureCampusManager::class])
                ->get('v1/campus-manager/checklists/staff-summary', [\App\Http\Controllers\Api\V1\CampusManager\StaffController::class, 'staffChecklistSummary']);
            Route::middleware(['api', 'auth:sanctum', \App\Http\Middleware\EnsureCampusManager::class])
                ->get('v1/mobile/campus-manager/checklists/staff-summary', [\App\Http\Controllers\Api\V1\CampusManager\StaffController::class, 'staffChecklistSummary']);

            // Emergency unread count – fallback when reverse proxy strips "api" prefix
            Route::middleware(['api', 'auth:sanctum', \App\Http\Middleware\EnsureCampusManager::class])
                ->get('v1/mobile/campus-manager/emergency/incidents/unread-count', [\App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'unreadCount']);
            Route::middleware(['api', 'auth:sanctum'])
                ->get('v1/mobile/warden/emergency/incidents/unread-count', [\App\Http\Controllers\Api\V1\CampusManager\EmergencyController::class, 'unreadCount']);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Force HTTPS for Livewire upload when X-Forwarded-Proto is https (fixes signature validation behind proxy)
        $middleware->prepend(\App\Http\Middleware\ForceHttpsForLivewireUpload::class);
        // Ensure Livewire temp upload dir exists before /livewire/upload-file (fixes 500)
        $middleware->prepend(\App\Http\Middleware\EnsureLivewireUploadDir::class);
        // Add Request ID middleware globally
        $middleware->append(\App\Http\Middleware\RequestId::class);
        $middleware->append(\App\Http\Middleware\PropagateRequestId::class);

        // Remove global idempotency middleware - only apply to specific API routes
        // $middleware->append(\App\Http\Middleware\Idempotency::class);
        
        // Add idempotency and tenant status middleware to API routes
        $middleware->alias([
            'idempotency' => \App\Http\Middleware\IdempotencyMiddleware::class,
            'tenant.status' => \App\Http\Middleware\CheckTenantStatus::class,
            'tenant.validate' => \App\Http\Middleware\ValidateTenantAccess::class,
            // Spatie permission aliases (Laravel 11 middleware config source of truth)
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Log 404s for staff-summary so we can see the path the server received (debug)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            try {
                if ($request && method_exists($request, 'path')) {
                    $path = $request->path();
                    if (str_contains((string) $path, 'staff-summary')) {
                        \Log::warning('Staff summary 404', ['path' => $path, 'fullUrl' => $request->fullUrl(), 'method' => $request->method()]);
                        @file_put_contents(
                            storage_path('logs/staff_summary_404.log'),
                            json_encode(['path' => $path, 'fullUrl' => $request->fullUrl(), 'method' => $request->method(), 'at' => now()->toIso8601String()]) . "\n",
                            FILE_APPEND | LOCK_EX
                        );
                    }
                }
            } catch (\Throwable $t) {
                // never let this handler cause a 500
            }
            return null;
        });

        // Customize unauthenticated response for mobile API routes
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            // #region agent log
            $logData = json_encode([
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'F',
                'location' => 'bootstrap/app.php:AuthenticationException',
                'message' => 'AuthenticationException caught',
                'data' => [
                    'url' => $request->fullUrl(),
                    'path' => $request->path(),
                    'is_mobile_route' => $request->is('api/v1/mobile/*'),
                    'has_auth_header' => $request->hasHeader('Authorization'),
                    'auth_header_preview' => $request->hasHeader('Authorization') ? substr($request->header('Authorization'), 0, 30) . '...' : 'missing',
                    'tenant_code' => $request->header('X-Tenant-Code'),
                    'expects_json' => $request->expectsJson(),
                ],
                'timestamp' => time() * 1000,
            ]);
            @file_put_contents('/tmp/debug.log', $logData . "\n", FILE_APPEND);
            // #endregion agent log
            
            // FORCE log to Laravel - this will definitely work
            \Log::channel('single')->error('🔍🔍🔍 AuthenticationException CAUGHT', [
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'is_mobile_route' => $request->is('api/v1/mobile/*'),
                'has_auth_header' => $request->hasHeader('Authorization'),
                'auth_header_preview' => $request->hasHeader('Authorization') ? substr($request->header('Authorization'), 0, 30) . '...' : 'missing',
                'tenant_code' => $request->header('X-Tenant-Code'),
                'expects_json' => $request->expectsJson(),
                'all_headers' => $request->headers->all(),
            ]);
            
            // ALWAYS return structured response for mobile API routes, regardless of expectsJson
            // Mobile apps may not send Accept: application/json header, but they still expect JSON
            if ($request->is('api/v1/mobile/*') || str_contains($request->path(), 'api/v1/mobile/')) {
                // Check if token is corrupted and provide helpful error message
                $authHeader = $request->header('Authorization');
                $detail = 'Authentication required. Please log in again.';
                
                if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                    $tokenValue = substr($authHeader, 7);
                    if ($tokenValue === '[object Object]' || 
                        (str_starts_with($tokenValue, '{') && (str_contains($tokenValue, '"_h"') || str_contains($tokenValue, '"_i"'))) ||
                        str_contains($tokenValue, '[object Object]')) {
                        $detail = 'Your authentication token appears to be corrupted. Please clear app data and log in again. Go to Settings → Apps → Student App → Storage → Clear Data, then reopen the app and log in.';
                    }
                }
                
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/unauthenticated',
                    'title' => 'Unauthenticated',
                    'status' => 401,
                    'detail' => $detail,
                ], 401);
            }
        });
        
        // Redirect 419 (Page Expired / CSRF mismatch) back with a message so user can retry
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            // Livewire upload sends multipart/form-data and may not set Accept: application/json; always return JSON 419 so client gets 419
            if ($request->is('livewire/upload-file') || str_contains($request->path(), 'livewire/upload-file')) {
                return response()->json(['message' => 'Session expired. Please refresh the page and try the upload again.'], 419);
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Page expired. Please refresh and try again.'], 419);
            }
            return redirect()->back()->withInput($request->except('password', '_token'))
                ->with('error', 'Your session expired. Please try again.');
        });

        // Log Livewire update (POST /livewire/update) errors for easier debugging (e.g. ppcu 500)
        $exceptions->report(function (Throwable $e) {
            $request = request();
            if ($request && (str_contains($request->path(), 'livewire/update') || $request->is('livewire/update'))) {
                \Log::error('Livewire update 500', [
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'url' => $request->fullUrl(),
                    'trace' => array_slice(array_map(fn ($t) => ($t['file'] ?? '') . ':' . ($t['line'] ?? '') . ' ' . ($t['function'] ?? ''), $e->getTrace()), 0, 12),
                ]);
            }
        });

        // Log and return readable error for Livewire file upload (tenant onboarding logo etc.)
        $exceptions->render(function (Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('livewire/upload-file') || str_contains($request->path(), 'livewire/upload-file')) {
                $msg = $e->getMessage();
                $class = get_class($e);
                $hypothesisId = 'H3';
                if (stripos($msg, 'permission') !== false || stripos($msg, 'writable') !== false || stripos($msg, 'directory') !== false) {
                    $hypothesisId = 'H1';
                } elseif (stripos($msg, 'size') !== false || stripos($msg, 'upload_max') !== false || stripos($msg, 'post_max') !== false) {
                    $hypothesisId = 'H2';
                } elseif (stripos($msg, 'signature') !== false || stripos($msg, 'Livewire') !== false) {
                    $hypothesisId = 'H3';
                } else {
                    $hypothesisId = 'H4';
                }
                \Log::error('Livewire upload-file error', [
                    'message' => $msg,
                    'class' => $class,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'hypothesisId' => $hypothesisId,
                ]);
                // #region agent log
                try {
                    $path = storage_path('logs/livewire_upload_debug.ndjson');
                    $line = json_encode([
                        'timestamp' => (int) (microtime(true) * 1000),
                        'event' => 'exception_rendered',
                        'hypothesisId' => $hypothesisId,
                        'sessionId' => 'debug-session',
                        'message' => $msg,
                        'exception' => $class,
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => array_slice(array_map(function ($t) {
                            return ($t['file'] ?? '') . ':' . ($t['line'] ?? '') . ' ' . ($t['function'] ?? '');
                        }, $e->getTrace()), 0, 5),
                    ]) . "\n";
                    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
                } catch (\Throwable $ignored) {}
                // #endregion
                // CSRF token mismatch = session expired; return 419 so client shows "Page expired, refresh"
                $isCsrfMismatch = stripos($msg, 'CSRF') !== false || stripos($msg, 'token mismatch') !== false
                    || $e instanceof \Illuminate\Session\TokenMismatchException;
                if ($isCsrfMismatch) {
                    return response()->json([
                        'message' => 'Session expired. Please refresh the page and try the upload again.',
                    ], 419);
                }
                return response()->json([
                    'message' => 'File upload failed: ' . $msg,
                    'exception' => $class,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'hypothesisId' => $hypothesisId,
                    'debug_trace' => array_slice(array_map(function ($t) {
                        return ($t['file'] ?? '') . ':' . ($t['line'] ?? '') . ' ' . ($t['function'] ?? '');
                    }, $e->getTrace()), 0, 8),
                ], 500);
            }
        });

        if (app()->environment('local')) {
            $exceptions->report(function (Throwable $e) {
                try {
                    $trace = $e->getTrace();
                    $top = $trace[0] ?? [];
                    \Log::error('DEV_EXCEPTION', [
                        'class' => get_class($e),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'top' => [
                            'file' => $top['file'] ?? null,
                            'line' => $top['line'] ?? null,
                            'function' => $top['function'] ?? null,
                            'class' => $top['class'] ?? null,
                        ],
                    ]);
                } catch (Throwable $ignored) {}
            });
        }
    })
    ->withProviders([
        AuthServiceProvider::class,
    ])
    ->create();
