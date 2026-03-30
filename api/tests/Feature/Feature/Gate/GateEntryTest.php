<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\GateEntry;
use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsGuard(): array
{
    $tenant = Tenant::factory()->create();
    $campus = Campus::factory()->create(['tenant_id' => $tenant->id]);
    $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id, 'campus_id' => $campus->id]);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Role::findOrCreate('Guard');
    $user->assignRole('Guard');
    Sanctum::actingAs($user);

    return compact('tenant', 'campus', 'hostel', 'user');
}

it('creates gate entry for guard', function (): void {
    $context = actingAsGuard();
    config(['features.gate_module' => true]);

    $payload = [
        'event' => 'entry',
        'occurred_at' => now()->toIso8601String(),
        'campus_id' => $context['campus']->id,
        'hostel_id' => $context['hostel']->id,
        'notes' => 'Student entered at gate',
    ];

    $response = $this->postJson('/api/v1/gate-entries', $payload);

    $response->assertCreated();

    expect(GateEntry::query()->where('tenant_id', $context['tenant']->id)->count())->toBe(1);
});

it('syncs offline entries with deduplication', function (): void {
    $context = actingAsGuard();
    config(['features.gate_module' => true]);

    $entries = [
        [
            'client_reference' => Str::uuid()->toString(),
            'event' => 'entry',
            'occurred_at' => now()->subMinutes(10)->toIso8601String(),
            'campus_id' => $context['campus']->id,
            'hostel_id' => $context['hostel']->id,
        ],
        [
            'client_reference' => Str::uuid()->toString(),
            'event' => 'exit',
            'occurred_at' => now()->subMinutes(5)->toIso8601String(),
        ],
    ];

    $this->postJson('/api/v1/gate-entries/sync', ['entries' => $entries])->assertCreated();

    expect(GateEntry::query()->where('tenant_id', $context['tenant']->id)->count())->toBe(2);

    $duplicate = ['entries' => [$entries[0]]];
    $this->postJson('/api/v1/gate-entries/sync', $duplicate)->assertCreated();

    expect(GateEntry::query()->where('tenant_id', $context['tenant']->id)->count())->toBe(2);
});

it('prevents access when feature disabled', function (): void {
    actingAsGuard();
    config(['features.gate_module' => false]);

    $this->postJson('/api/v1/gate-entries', [
        'event' => 'entry',
        'occurred_at' => now()->toIso8601String(),
    ])->assertNotFound();
});
