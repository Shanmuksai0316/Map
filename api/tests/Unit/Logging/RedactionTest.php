<?php

use App\Logging\RedactSensitiveDataProcessor;
use Monolog\Level;
use Monolog\LogRecord;

test('redaction processor masks email addresses', function () {
    $processor = new RedactSensitiveDataProcessor();
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable(),
        channel: 'test',
        level: Level::Info,
        message: 'User john@example.com logged in',
        context: []
    );
    
    $processed = $processor($record);
    
    expect($processed->message)->toBe('User [REDACTED_EMAIL] logged in');
});

test('redaction processor masks phone numbers', function () {
    $processor = new RedactSensitiveDataProcessor();
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable(),
        channel: 'test',
        level: Level::Info,
        message: 'User with phone 9876543210 called',
        context: []
    );
    
    $processed = $processor($record);
    
    expect($processed->message)->toBe('User with phone [REDACTED_PHONE] called');
});

test('redaction processor masks sensitive context keys', function () {
    $processor = new RedactSensitiveDataProcessor();
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable(),
        channel: 'test',
        level: Level::Info,
        message: 'User login attempt',
        context: [
            'email' => 'user@example.com',
            'password' => 'secret123',
            'phone' => '9876543210',
            'user_id' => 123,
            'action' => 'login'
        ]
    );
    
    $processed = $processor($record);
    
    expect($processed->context['email'])->toBe('[REDACTED]');
    expect($processed->context['password'])->toBe('[REDACTED]');
    expect($processed->context['phone'])->toBe('[REDACTED]');
    expect($processed->context['user_id'])->toBe(123);
    expect($processed->context['action'])->toBe('login');
});

test('redaction processor handles nested context arrays', function () {
    $processor = new RedactSensitiveDataProcessor();
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable(),
        channel: 'test',
        level: Level::Info,
        message: 'Complex log entry',
        context: [
            'user' => [
                'email' => 'user@example.com',
                'phone' => '9876543210',
                'id' => 123
            ],
            'request' => [
                'url' => '/api/test',
                'token' => 'abc123def456'
            ]
        ]
    );
    
    $processed = $processor($record);
    
    expect($processed->context['user']['email'])->toBe('[REDACTED]');
    expect($processed->context['user']['phone'])->toBe('[REDACTED]');
    expect($processed->context['user']['id'])->toBe(123);
    expect($processed->context['request']['url'])->toBe('/api/test');
    expect($processed->context['request']['token'])->toBe('[REDACTED]');
});

test('redaction processor masks api tokens in messages', function () {
    $processor = new RedactSensitiveDataProcessor();
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable(),
        channel: 'test',
        level: Level::Info,
        message: 'API call with token abc123def456ghi789jkl012mno345pqr678',
        context: []
    );
    
    $processed = $processor($record);
    
    expect($processed->message)->toBe('API call with token [REDACTED_TOKEN]');
});
