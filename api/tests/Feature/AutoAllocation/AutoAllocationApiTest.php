<?php

use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomBed;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function setupAutoAllocationContext(): array
{
    $tenant = Tenant::factory()->create();
    Role::findOrCreate('Campus Manager');

    $hostel = Hostel::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'HSTL-A',
    ]);

    $room = Room::factory()->create([
        'tenant_id' => $tenant->id,
        'hostel_id' => $hostel->id,
        'capacity' => 2,
        'room_type' => 'standard',
    ]);

    $beds = RoomBed::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'hostel_id' => $hostel->id,
        'room_id' => $room->id,
        'status' => 'available',
    ]);

    $students = Student::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'hostel_id' => $hostel->id,
        'preferred_hostel_id' => $hostel->id,
        'preferred_room_type' => 'standard',
        'preferred_sharing' => 'double',
    ]);

    $manager = User::factory()->create([
        'tenant_id' => $tenant->id,
        'kind' => 'staff',
    ]);
    $manager->assignRole('Campus Manager');

    Sanctum::actingAs($manager);

    return compact('tenant', 'hostel', 'room', 'beds', 'students', 'manager');
}

it('previews auto allocation suggestions', function (): void {
    $context = setupAutoAllocationContext();

    $response = postJson('/v1/campus-manager/auto-allocation/preview', [
        'hostel_id' => $context['hostel']->id,
        'limit' => 2,
    ]);

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment([
            'preferred_hostel_id' => $context['hostel']->id,
        ]);
});

it('commits allocations from payload', function (): void {
    $context = setupAutoAllocationContext();

    $preview = postJson('/v1/campus-manager/auto-allocation/preview', [
        'hostel_id' => $context['hostel']->id,
        'limit' => 2,
    ])->json('data');

    $allocations = array_map(
        fn ($suggestion) => [
            'student_id' => $suggestion['student_id'],
            'room_bed_id' => $suggestion['room_bed_id'],
            'effective_from' => now()->toIso8601String(),
        ],
        array_filter($preview, fn ($item) => $item['room_bed_id'])
    );

    $response = postJson('/v1/campus-manager/auto-allocation/commit', [
        'allocations' => $allocations,
        'note' => 'Bulk auto allocation',
    ]);

    $response->assertCreated();

    foreach ($allocations as $allocation) {
        $this->assertDatabaseHas('room_allocations', [
            'student_id' => $allocation['student_id'],
            'room_bed_id' => $allocation['room_bed_id'],
            'note' => 'Bulk auto allocation',
        ]);
    }
});

it('downloads template for auto allocation students', function (): void {
    $context = setupAutoAllocationContext();

    $response = get('/v1/campus-manager/auto-allocation/templates/auto-allocation');

    $response->assertOk();
    $response->assertHeader('content-disposition');
});

