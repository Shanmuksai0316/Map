<?php

use App\Enums\OutPassStatus;
use App\Enums\OutPassType;
use App\Models\Campus;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\Sanctum;

it('creates an out pass with idempotency', function (): void {
    Date::setTestNow(now());

    $tenant = Tenant::factory()->create();
    $campus = Campus::factory()->create(['tenant_id' => $tenant->id]);
    $hostel = Hostel::factory()->create([
        'tenant_id' => $tenant->id,
        'campus_id' => $campus->id,
    ]);
    $student = User::factory()->student()->create([
        'tenant_id' => $tenant->id,
    ]);

    $payload = [
        'hostel_id' => $hostel->id,
        'reason' => OutPassType::NORMAL->value,
        'overnight' => false,
        'valid_until' => now()->addHours(6)->toISOString(),
    ];

    Sanctum::actingAs($student);

    $response = $this->withHeader('Idempotency-Key', 'abc-123')
        ->postJson('/api/v1/outpasses', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.status', OutPassStatus::PENDING->value);

    expect(OutPass::where('tenant_id', $tenant->id)->count())->toBe(1);

    Sanctum::actingAs($student);

    $repeat = $this->withHeader('Idempotency-Key', 'abc-123')
        ->postJson('/api/v1/outpasses', $payload);

    $repeat->assertCreated();

    expect(OutPass::where('tenant_id', $tenant->id)->count())->toBe(1);

    Date::setTestNow();
});
