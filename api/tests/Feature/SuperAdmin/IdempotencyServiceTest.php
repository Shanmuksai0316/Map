<?php

use App\Services\IdempotencyService;
use Illuminate\Support\Facades\DB;

it('enforces unique idempotency keys per action', function () {
    $service = app(IdempotencyService::class);
    $key = $service->assertUnique('tenant_activation', 'KEY-123', 1, 'tenant-1', ['foo' => 'bar']);

    expect($key)->toBe('KEY-123');

    // Second attempt with same key/action should throw
    expect(fn () => $service->assertUnique('tenant_activation', 'KEY-123', 1, 'tenant-1'))
        ->toThrow(\RuntimeException::class);
});

it('stores response snapshots', function () {
    $service = app(IdempotencyService::class);
    $key = $service->assertUnique('tenant_activation', 'KEY-RESP', 1, 'tenant-1');

    $service->storeResponse('tenant_activation', $key, ['ok' => true]);

    $row = DB::table('idempotency_keys')->where('key', $key)->first();
    expect($row)->not->toBeNull();
    expect(json_decode($row->response_snapshot, true))->toMatchArray(['ok' => true]);
});

