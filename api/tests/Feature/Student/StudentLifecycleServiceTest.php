<?php

use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use App\Models\User;
use App\Services\Students\StudentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeStudentWithAllocation(): array
{
    $tenantId = (string) Str::uuid();

    $campus = Campus::create([
        'tenant_id' => $tenantId,
        'code' => 'CMP-' . Str::upper(Str::random(3)),
        'name' => 'Test Campus',
        'address' => [],
    ]);

    $hostel = Hostel::create([
        'tenant_id' => $tenantId,
        'campus_id' => $campus->id,
        'code' => 'HST-' . Str::upper(Str::random(3)),
        'name' => 'Test Hostel',
        'gender_mode' => 'Coed',
        'curfew_time' => '22:00:00',
        'overnight_enabled' => false,
        'settings' => [],
    ]);

    $room = Room::create([
        'tenant_id' => $tenantId,
        'campus_id' => $campus->id,
        'hostel_id' => $hostel->id,
        'block_code' => 'A',
        'floor_code' => 'F1',
        'number' => '101',
        'capacity' => 4,
        'room_type' => 'double',
        'is_active' => true,
    ]);

    $bed = RoomBed::create([
        'tenant_id' => $tenantId,
        'hostel_id' => $hostel->id,
        'room_id' => $room->id,
        'code' => 'A1',
        'status' => 'occupied',
    ]);

    $studentUser = User::factory()->create([
        'tenant_id' => $tenantId,
    ]);

    $student = Student::create([
        'tenant_id' => $tenantId,
        'user_id' => $studentUser->id,
        'hostel_id' => $hostel->id,
        'map_student_id' => 'MAP-' . Str::random(6),
        'student_uid' => 'STU-' . Str::random(6),
    ]);

    $allocation = RoomAllocation::create([
        'tenant_id' => $tenantId,
        'student_id' => $student->id,
        'room_bed_id' => $bed->id,
        'hostel_id' => $hostel->id,
        'effective_from' => now()->subMonth(),
        'is_active' => true,
    ]);

    return [
        'tenant_id' => $tenantId,
        'campus' => $campus,
        'hostel' => $hostel,
        'room' => $room,
        'bed' => $bed,
        'student' => $student,
        'allocation' => $allocation,
    ];
}

it('archives student and releases bed', function (): void {
    $context = makeStudentWithAllocation();

    $manager = User::factory()->create([
        'tenant_id' => $context['tenant_id'],
    ]);

    $this->actingAs($manager);

    $service = app(StudentLifecycleService::class);
    $service->archive($context['student'], Carbon::parse('2025-11-21'), 'Graduated');

    $context['student']->refresh();
    $context['allocation']->refresh();
    $context['bed']->refresh();

    expect($context['student']->archived_at)->not->toBeNull();
    expect($context['student']->archived_reason)->toBe('Graduated');
    expect($context['allocation']->is_active)->toBeFalse();
    expect($context['allocation']->checkout_status)->toBe('archived');
    expect($context['bed']->status)->toBe('available');
});

it('restores an archived student', function (): void {
    $context = makeStudentWithAllocation();
    $manager = User::factory()->create([
        'tenant_id' => $context['tenant_id'],
    ]);
    $this->actingAs($manager);

    $service = app(StudentLifecycleService::class);
    $service->archive($context['student'], Carbon::now(), 'Graduated');

    $service->restore($context['student']);
    $context['student']->refresh();

    expect($context['student']->archived_at)->toBeNull();
    expect($context['student']->archived_reason)->toBeNull();
});
