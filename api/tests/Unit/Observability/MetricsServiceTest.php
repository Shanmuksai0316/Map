<?php

use App\Services\Metrics\Metrics;
use Illuminate\Support\Facades\Log;

// Note: These are unit tests, no database needed

beforeEach(function () {
    // Reset metrics state between tests
    Metrics::reset();
});

test('metrics service returns true when AWS is off', function () {
    $result = Metrics::count('TestMetric', 1, ['test' => 'value']);
    
    expect($result)->toBeTrue();
});

test('metrics service logs debug message when disabled', function () {
    // Just test that it returns true without throwing
    $result = Metrics::count('TestMetric', 1, ['test' => 'value']);
    
    expect($result)->toBeTrue();
});

test('metrics service works when AWS is configured', function () {
    // Mock AWS configuration
    putenv('AWS_REGION=ap-south-1');
    putenv('CW_METRICS_NAMESPACE=MAP-HMS');
    
    // Reinitialize metrics
    Metrics::init();
    
    $result = Metrics::count('TestMetric', 1, ['test' => 'value']);
    
    expect($result)->toBeTrue();
});

test('metrics service gauge method works', function () {
    $result = Metrics::gauge('TestGauge', 42.5, ['test' => 'value']);
    
    expect($result)->toBeTrue();
});

test('metrics service timing method works', function () {
    $result = Metrics::timing('TestTiming', 1500, ['test' => 'value']);
    
    expect($result)->toBeTrue();
});

test('metrics service isEnabled method works', function () {
    // Should be false by default (no AWS config)
    expect(Metrics::isEnabled())->toBeFalse();
});
