<?php

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

function checkoutContext(): array
{
    $tenant = Tenant::factory()->create();
    Role::findOrCreate('Campus Manager');

    $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
    $room = Room::factory()->create(['tenant_id' => $tenant->id, 'hostel_id' => $hostel->id]);
    $bed = RoomBed::factory()->create([
        'tenant_id' => $tenant->id,
        'hostel_id' => $hostel->id,
        'room_id' => $room->id,
        'status' => 'occupied',
    ]);

    $student = Student::factory()->create([
        'tenant_id' => $tenant->id,
        'hostel_id' => $hostel->id,
    ]);

    $allocation = RoomAllocation::factory()->create([
        'tenant_id' => $tenant->id,
        'student_id' => $student->id,
        'room_bed_id' => $bed->id,
        'hostel_id' => $hostel->id,
        'effective_from' => now()->subMonths(3),
        'expected_checkout_at' => now()->addWeek(),
    ]);

    $manager = User::factory()->create(['tenant_id' => $tenant->id]);
    $manager->assignRole('Campus Manager');

    return compact('tenant', 'hostel', 'room', 'bed', 'student', 'allocation', 'manager');
}

it('starts a checkout workflow', function (): void {
    $context = checkoutContext();
    Sanctum::actingAs($context['manager']);

    $response = postJson('/v1/campus-manager/checkouts/' . $context['allocation']->id . '/start', [
        'inspection_passed' => true,
        'keys_collected' => false,
        'notes' => 'Pending key return',
    ]);

    $response->assertOk();

    $context['allocation']->refresh();
    expect($context['allocation']->checkout_status)->toBe('in_progress');
    expect($context['allocation']->checkoutChecklist)->not->toBeNull();
});

it('completes a checkout workflow and archives student', function (): void {
    $context = checkoutContext();
    Sanctum::actingAs($context['manager']);

    postJson('/v1/campus-manager/checkouts/' . $context['allocation']->id . '/start')->assertOk();

    $response = postJson('/v1/campus-manager/checkouts/' . $context['allocation']->id . '/complete', [
        'inspection_passed' => true,
        'keys_collected' => true,
        'dues_cleared' => true,
    ]);

    $response->assertOk();

    $context['allocation']->refresh();
    expect($context['allocation']->checkout_status)->toBe('completed');
    expect($context['student']->fresh()->archived_at)->not->toBeNull();
});
