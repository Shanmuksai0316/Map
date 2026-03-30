<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function seedTenantContext(): array
{
    $tenant = Tenant::factory()->create();
    $campus = Campus::factory()->create(['tenant_id' => $tenant->id]);
    $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id, 'campus_id' => $campus->id]);

    return compact('tenant', 'campus', 'hostel');
}

function actingAsRoomModuleManager(): array
{
    $context = seedTenantContext();

    $manager = User::factory()->campusManager()->create(['tenant_id' => $context['tenant']->id]);
    Role::findOrCreate('Campus Manager');
    $manager->assignRole('Campus Manager');

    Sanctum::actingAs($manager);

    return array_merge($context, ['manager' => $manager]);
}

it('lists rooms for tenant', function (): void {
    $context = actingAsRoomModuleManager();

    Room::factory()->count(2)->create([
        'tenant_id' => $context['tenant']->id,
        'campus_id' => $context['campus']->id,
        'hostel_id' => $context['hostel']->id,
    ]);

    $response = $this->getJson('/api/v1/rooms');

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('creates room with beds', function (): void {
    $context = actingAsRoomModuleManager();

    $payload = [
        'campus_id' => $context['campus']->id,
        'hostel_id' => $context['hostel']->id,
        'number' => '101',
        'capacity' => 4,
        'beds' => [
            ['code' => 'A'],
            ['code' => 'B', 'status' => 'blocked'],
        ],
    ];

    $response = $this->postJson('/api/v1/rooms', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.number', '101')
        ->assertJsonCount(2, 'data.beds');

    expect(Room::query()->count())->toBe(1);
});

it('updates room and beds', function (): void {
    $context = actingAsRoomModuleManager();

    $room = Room::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'campus_id' => $context['campus']->id,
        'hostel_id' => $context['hostel']->id,
    ]);

    $bed = $room->beds()->create([
        'tenant_id' => $context['tenant']->id,
        'hostel_id' => $context['hostel']->id,
        'code' => 'A',
    ]);

    $response = $this->putJson("/api/v1/rooms/{$room->id}", [
        'number' => '102',
        'beds' => [
            ['id' => $bed->id, 'code' => 'AA', 'status' => 'occupied'],
            ['code' => 'B'],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.number', '102')
        ->assertJsonCount(2, 'data.beds');
});

it('deletes room', function (): void {
    $manager = actingAsRoomModuleManager();

    $room = Room::factory()->create([
        'tenant_id' => $manager['tenant']->id,
        'campus_id' => $manager['campus']->id,
        'hostel_id' => $manager['hostel']->id,
    ]);

    $this->deleteJson("/api/v1/rooms/{$room->id}")
        ->assertNoContent();

    expect(Room::query()->count())->toBe(0);
});
