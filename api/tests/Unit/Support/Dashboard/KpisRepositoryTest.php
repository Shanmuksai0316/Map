<?php

use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\Tenant;
use App\Support\Dashboard\KpisRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('handles string tenant identifiers for dashboard trends', function () {
    Carbon::setTestNow('2025-11-12 10:00:00');

    $tenant = Tenant::factory()->create();

    AttendanceSession::factory()->create([
        'tenant_id' => $tenant->id,
        'created_at' => now()->subDay(),
        'closed_at' => now()->subDay()->addHour(),
    ]);

    $repository = app(KpisRepository::class);

    $trend = $repository->attendanceClosure7dTrend($tenant->id);
    expect($trend)
        ->toHaveKeys(['dates', 'percentages'])
        ->and($trend['dates'])->toHaveCount(7)
        ->and($trend['percentages'])->toHaveCount(7);
});

it('supports string tenant identifiers for ticket aggregates', function () {
    $tenant = Tenant::factory()->create();

    $repository = app(KpisRepository::class);

    $result = $repository->ticketsOpenByPriority($tenant->id);

    expect($result)
        ->toHaveKeys(['critical', 'high', 'medium', 'low']);
});

