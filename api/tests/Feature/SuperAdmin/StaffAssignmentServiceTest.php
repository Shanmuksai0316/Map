<?php

use App\Models\Hostel;
use App\Models\User;
use App\Services\StaffAssignmentService;

it('blocks duplicate role assignment per hostel', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $hostel = Hostel::factory()->create();
    $warden1 = User::factory()->create(['tenant_id' => $hostel->tenant_id, 'kind' => 'staff', 'is_map_staff' => true]);
    $warden2 = User::factory()->create(['tenant_id' => $hostel->tenant_id, 'kind' => 'staff', 'is_map_staff' => true]);
    $warden1->assignRole('Warden');
    $warden2->assignRole('Warden');

    $service = app(StaffAssignmentService::class);
    $service->assignStaff($warden1, [
        'tenant_id' => $hostel->tenant_id,
        'hostel_id' => $hostel->id,
        'role' => 'Warden',
    ]);

    expect(function () use ($service, $warden2, $hostel) {
        $service->assignStaff($warden2, [
            'tenant_id' => $hostel->tenant_id,
            'hostel_id' => $hostel->id,
            'role' => 'Warden',
        ]);
    })->toThrow(\Exception::class, 'already assigned');
});

it('allows first assignment for role per hostel', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $hostel = Hostel::factory()->create();
    $warden = User::factory()->create(['tenant_id' => $hostel->tenant_id, 'kind' => 'staff', 'is_map_staff' => true]);
    $warden->assignRole('Warden');

    $service = app(StaffAssignmentService::class);
    $service->assignStaff($warden, [
        'tenant_id' => $hostel->tenant_id,
        'hostel_id' => $hostel->id,
        'role' => 'Warden',
    ]);

    $assignment = $service->getActiveAssignment($warden);
    expect($assignment)->not->toBeNull();
    expect($assignment->hostel_id)->toBe($hostel->id);
});

