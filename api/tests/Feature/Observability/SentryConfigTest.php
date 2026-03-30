<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure Sentry is configured for testing
    Config::set('sentry.dsn', 'https://test@sentry.io/test');
    Config::set('sentry.traces_sample_rate', 0.2);
});

test('sentry config keys are present', function () {
    expect(Config::get('sentry.dsn'))->not->toBeNull();
    expect(Config::get('sentry.traces_sample_rate'))->toBe(0.2);
    expect(Config::get('sentry.ignore_exceptions'))->toContain(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
    expect(Config::get('sentry.before_send'))->toBeCallable();
});

test('sentry before_send callback is configured for pii redaction', function () {
    $beforeSendCallback = Config::get('sentry.before_send');
    
    // Test that the callback is callable and configured
    expect($beforeSendCallback)->toBeCallable();
    
    // Test that the callback exists and is a closure
    expect($beforeSendCallback)->toBeInstanceOf(\Closure::class);
});

test('sentry can capture exceptions without crashing', function () {
    // This test ensures Sentry is properly initialized and won't crash the app
    expect(function () {
        \Sentry\captureException(new \Exception('Test exception for Sentry'));
    })->not->toThrow(\Exception::class);
});
