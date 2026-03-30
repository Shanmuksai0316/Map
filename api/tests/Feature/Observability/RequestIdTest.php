<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('GET /healthz returns X-Request-Id header', function () {
    $response = $this->get('/healthz');
    
    expect($response->status())->toBe(200);
    expect($response->headers->get('X-Request-Id'))->not->toBeNull();
    expect($response->headers->get('X-Request-Id'))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});

test('request ID is propagated in logs', function () {
    // Clear any existing log context
    Log::withoutContext();
    
    $response = $this->get('/healthz');
    $requestId = $response->headers->get('X-Request-Id');
    
    // Log a test message
    Log::info('Test message for request ID propagation');
    
    // Get the log file content
    $logFile = storage_path('logs/laravel.log');
    
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        
        // Check that the log contains the request ID
        expect($logContent)->toContain($requestId);
    }
});

test('custom X-Request-Id header is preserved', function () {
    $customRequestId = 'custom-request-' . uniqid();
    
    $response = $this->withHeaders([
        'X-Request-Id' => $customRequestId
    ])->get('/healthz');
    
    expect($response->status())->toBe(200);
    expect($response->headers->get('X-Request-Id'))->toBe($customRequestId);
});

test('request ID is unique for each request', function () {
    $response1 = $this->get('/healthz');
    $response2 = $this->get('/healthz');
    
    $requestId1 = $response1->headers->get('X-Request-Id');
    $requestId2 = $response2->headers->get('X-Request-Id');
    
    expect($requestId1)->not->toBe($requestId2);
    expect($requestId1)->not->toBeNull();
    expect($requestId2)->not->toBeNull();
});

test('request ID middleware works on API routes', function () {
    // Test with a simple API route that exists
    $response = $this->get('/api/v1/tickets');
    
    // Should get 401 (unauthorized) or 200, but should have request ID
    expect($response->headers->get('X-Request-Id'))->not->toBeNull();
});
