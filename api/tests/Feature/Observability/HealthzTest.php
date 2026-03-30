<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

test('healthz endpoint returns proper structure', function () {
    $response = $this->get('/healthz');
    
    expect($response->status())->toBe(200);
    
    $data = $response->json();
    
    // Check structure
    expect($data)->toHaveKeys(['ok', 'checks', 'version', 'time']);
    expect($data['checks'])->toHaveKeys(['db', 'cache', 'queue']);
    expect($data['version'])->toHaveKeys(['app', 'git']);
    
    // Check data types
    expect($data['ok'])->toBeBool();
    expect($data['checks']['db'])->toBeString();
    expect($data['checks']['cache'])->toBeString();
    expect($data['checks']['queue'])->toBeString();
    expect($data['version']['app'])->toBeString();
    expect($data['version']['git'])->toBeString();
    expect($data['time'])->toBeString();
});

test('healthz endpoint returns all checks as ok', function () {
    $response = $this->get('/healthz');
    
    expect($response->status())->toBe(200);
    
    $data = $response->json();
    
    // All checks should be ok
    expect($data['ok'])->toBeTrue();
    expect($data['checks']['db'])->toBe('ok');
    expect($data['checks']['cache'])->toBe('ok');
    expect($data['checks']['queue'])->toBeIn(['ok', 'sync']);
});

test('healthz endpoint includes version information', function () {
    $response = $this->get('/healthz');
    
    expect($response->status())->toBe(200);
    
    $data = $response->json();
    
    // Version should be present
    expect($data['version']['app'])->not->toBeEmpty();
    expect($data['version']['git'])->toBeString();
    
    // App version should default to v1.0 if not configured
    expect($data['version']['app'])->toBe('v1.0');
    
    // Git SHA should be present (even if 'unknown' when not in git repo)
    expect($data['version']['git'])->toBeIn(['unknown', '']);
});

test('healthz endpoint includes timestamp', function () {
    $response = $this->get('/healthz');
    
    expect($response->status())->toBe(200);
    
    $data = $response->json();
    
    // Time should be a valid ISO 8601 timestamp
    expect($data['time'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
    
    // Should be recent (within last minute)
    $timestamp = \Carbon\Carbon::parse($data['time']);
    expect($timestamp->isAfter(now()->subMinute()))->toBeTrue();
});

test('healthz endpoint includes X-Request-Id header', function () {
    $response = $this->get('/healthz');
    
    expect($response->status())->toBe(200);
    expect($response->headers->get('X-Request-Id'))->not->toBeNull();
    expect($response->headers->get('X-Request-Id'))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});
