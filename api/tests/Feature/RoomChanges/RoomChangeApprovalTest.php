<?php

use App\Domain\RoomChanges\Models\RoomChange;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function setupRoomChangeContext(): array
{
    $tenant = Tenant::factory()->create();
    Role::findOrCreate('Campus Manager');

    $hostel = Hostel::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $room = Room::factory()->create([
        'tenant_id' => $tenant->id,
        'hostel_id' => $hostel->id,
    ]);

    $targetBed = RoomBed::factory()->create([
        'tenant_id' => $tenant->id,
        'hostel_id' => $hostel->id,
        'room_id' => $room->id,
        'status' => 'available',
        'code' => 'B1',
    ]);

    $student = Student::factory()->create([
        'tenant_id' => $tenant->id,
        'hostel_id' => $hostel->id,
    ]);

    $currentBed = RoomBed::factory()->create([
        'tenant_id' => $tenant->id,
        'hostel_id' => $hostel->id,
        'room_id' => $room->id,
        'status' => 'occupied',
        'code' => 'A1',
    ]);

    RoomAllocation::factory()->create([
        'tenant_id' => $tenant->id,
        'student_id' => $student->id,
        'room_bed_id' => $currentBed->id,
        'hostel_id' => $hostel->id,
        'effective_from' => now()->subMonth(),
    ]);

    $roomChange = RoomChange::factory()->create([
        'tenant_id' => $tenant->id,
        'student_id' => $student->id,
        'hostel_id' => $hostel->id,
        'status' => 'pending',
    ]);

    $manager = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);
    $manager->assignRole('Campus Manager');

    return compact('tenant', 'hostel', 'room', 'student', 'roomChange', 'manager', 'targetBed');
}

it('approves a room change request and reallocates bed', function (): void {
    $context = setupRoomChangeContext();

    Sanctum::actingAs($context['manager']);

    $response = postJson('/v1/campus-manager/room-changes/' . $context['roomChange']->id . '/approve', [
        'room_bed_id' => $context['targetBed']->id,
        'effective_from' => Carbon::now()->toIso8601String(),
    ]);

    $response->assertOk();

    $context['roomChange']->refresh();
    expect($context['roomChange']->status)->toBe('approved');
    expect($context['roomChange']->approved_by)->toBe($context['manager']->id);

    $allocation = RoomAllocation::where('student_id', $context['student']->id)
        ->where('room_bed_id', $context['targetBed']->id)
        ->first();

    expect($allocation)->not()->toBeNull();
});

it('rejects a room change request with reason', function (): void {
    $context = setupRoomChangeContext();

    Sanctum::actingAs($context['manager']);

    $response = postJson('/v1/campus-manager/room-changes/' . $context['roomChange']->id . '/reject', [
        'reason' => 'Beds unavailable',
    ]);

    $response->assertOk();

    $context['roomChange']->refresh();
    expect($context['roomChange']->status)->toBe('rejected');
    expect($context['roomChange']->rejection_reason)->toBe('Beds unavailable');
});
