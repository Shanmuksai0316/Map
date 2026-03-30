<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$request = Request::capture();

// #region agent log - H5: did request reach PHP? (if no log entry, nginx/proxy returned 500 before PHP)
$reqPath = $request->path();
if (str_contains($reqPath, 'livewire/upload-file')) {
    $h5Log = __DIR__ . '/../storage/logs/livewire_upload_debug.ndjson';
    @file_put_contents($h5Log, json_encode([
        'timestamp' => (int) (microtime(true) * 1000),
        'event' => 'index_php_reached',
        'hypothesisId' => 'H5',
        'path' => $reqPath,
        'method' => $request->method(),
    ]) . "\n", FILE_APPEND | LOCK_EX);
}
// #endregion

// #region agent log - EARLIEST POSSIBLE LOGGING
if (str_contains($reqPath, 'mobile') || str_contains($request->path(), 'tickets')) {
    $logData = json_encode([
        'sessionId' => 'debug-session',
        'runId' => 'run1',
        'hypothesisId' => 'EARLY',
        'location' => 'public/index.php',
        'message' => 'Request captured at entry point',
        'data' => [
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'method' => $request->method(),
            'has_auth_header' => $request->hasHeader('Authorization'),
            'auth_header_preview' => $request->hasHeader('Authorization') ? substr($request->header('Authorization'), 0, 30) . '...' : 'missing',
            'tenant_code' => $request->header('X-Tenant-Code'),
        ],
        'timestamp' => time() * 1000,
    ]);
    @file_put_contents('/tmp/debug.log', $logData . "\n", FILE_APPEND);
}
// #endregion agent log

$app->handleRequest($request);
