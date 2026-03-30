<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('json logging includes request_id and tenant_id context', function () {
    // Set up request context
    $requestId = 'test-request-' . uniqid();
    $tenantId = 1;
    
    // Mock request context
    Log::withContext([
        'request_id' => $requestId,
        'tenant_id' => $tenantId,
        'user_id' => 123
    ]);
    
    // Log a test message
    Log::info('Test log message', [
        'action' => 'test_action',
        'data' => 'test_data'
    ]);
    
    // Get the log file content
    $logFile = storage_path('logs/laravel.log');
    
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        
        // Check that the log contains our context
        expect($logContent)->toContain($requestId);
        expect($logContent)->toContain('"tenant_id":1');
        expect($logContent)->toContain('"user_id":123');
        expect($logContent)->toContain('Test log message');
    }
});

test('cloudwatch log channel is configured', function () {
    $config = config('logging.channels.cloudwatch');
    
    expect($config)->not->toBeNull();
    expect($config['driver'])->toBe('monolog');
    expect($config['formatter'])->toBe(\Monolog\Formatter\JsonFormatter::class);
    expect($config['processors'])->toContain(\App\Logging\RedactSensitiveDataProcessor::class);
});

test('log channel can be switched to cloudwatch', function () {
    // Test that we can switch to cloudwatch channel
    config(['logging.default' => 'cloudwatch']);
    
    expect(config('logging.default'))->toBe('cloudwatch');
    
    // Log a test message to cloudwatch channel
    Log::channel('cloudwatch')->info('CloudWatch test message', [
        'test' => true,
        'timestamp' => now()->toISOString()
    ]);
    
    // This should not throw an exception
    expect(true)->toBeTrue();
});
