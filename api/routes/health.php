<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Health Check Routes
|--------------------------------------------------------------------------
|
| These routes provide health check endpoints for monitoring and alerting.
| They are public and don't require authentication.
|
*/

// Basic health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'app' => config('app.name'),
        'env' => config('app.env'),
    ]);
});

// Detailed health check
Route::get('/health/detailed', function () {
    $checks = [];
    
    // Database check
    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (\Exception $e) {
        $checks['database'] = 'error: ' . $e->getMessage();
    }
    
    // Cache check
    try {
        \Cache::put('health_check', true, 60);
        $checks['cache'] = \Cache::get('health_check') ? 'ok' : 'error';
    } catch (\Exception $e) {
        $checks['cache'] = 'error: ' . $e->getMessage();
    }
    
    // Storage check
    $checks['storage_writable'] = is_writable(storage_path()) ? 'ok' : 'error';
    
    // OPcache check
    $checks['opcache'] = function_exists('opcache_get_status') && opcache_get_status() ? 'enabled' : 'disabled';
    
    // Determine overall status
    $hasErrors = collect($checks)->contains(function ($value) {
        return str_contains($value, 'error');
    });
    
    return response()->json([
        'status' => $hasErrors ? 'degraded' : 'healthy',
        'timestamp' => now()->toIso8601String(),
        'checks' => $checks,
    ], $hasErrors ? 503 : 200);
});

// Admin panel specific health check
Route::get('/health/admin', function () {
    try {
        // Check if admin panel route is accessible
        $response = \Http::get(url('/admin'));
        $status = in_array($response->status(), [200, 302]) ? 'ok' : 'error';
        
        return response()->json([
            'status' => $status === 'ok' ? 'healthy' : 'unhealthy',
            'admin_panel_status' => $status,
            'http_code' => $response->status(),
            'timestamp' => now()->toIso8601String(),
        ], $status === 'ok' ? 200 : 503);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'admin_panel_status' => 'error',
            'error' => $e->getMessage(),
            'timestamp' => now()->toIso8601String(),
        ], 503);
    }
});

// FilamentUser interface check
Route::get('/health/filament-user', function () {
    try {
        $user = new \App\Models\User();
        $implementsInterface = $user instanceof \Filament\Models\Contracts\FilamentUser;
        $hasMethod = method_exists($user, 'canAccessPanel');
        
        $status = $implementsInterface && $hasMethod ? 'ok' : 'error';
        
        return response()->json([
            'status' => $status === 'ok' ? 'healthy' : 'critical',
            'implements_filament_user' => $implementsInterface,
            'has_can_access_panel_method' => $hasMethod,
            'note' => $status === 'ok' ? 'User model correctly implements FilamentUser' : 'CRITICAL: User model missing FilamentUser interface or canAccessPanel method',
            'timestamp' => now()->toIso8601String(),
        ], $status === 'ok' ? 200 : 500);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'error' => $e->getMessage(),
            'timestamp' => now()->toIso8601String(),
        ], 500);
    }
});


