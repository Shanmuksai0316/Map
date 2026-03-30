<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\Onboarding\PreflightService;

it('fails preflight when no hostels are configured', function () {
    $tenant = Tenant::factory()->create(['code' => 'MAP-NOHOST', 'status' => \App\Enums\TenantStatus::PROVISIONING]);
    $service = app(PreflightService::class);

    $result = $service->evaluate($tenant, [
        'hostels' => [],
        'staff' => [],
        'contacts' => [],
    ]);

    expect($result['passed'])->toBeFalse();
    expect(collect($result['errors'])->pluck('message'))->toContain('At least one hostel must be configured');
});

it('fails preflight when Campus Manager missing', function () {
    $tenant = Tenant::factory()->create(['code' => 'MAP-NOCM', 'status' => \App\Enums\TenantStatus::PROVISIONING]);
    $service = app(PreflightService::class);

    $result = $service->evaluate($tenant, [
        'hostels' => [],
        'staff' => [],
        'contacts' => [],
    ]);

    expect($result['passed'])->toBeFalse();
    expect(collect($result['errors'])->pluck('message'))->toContain('Campus Manager must be assigned at tenant scope');
});

it('passes preflight with hostel + rooms + roles + contacts', function () {
    $tenant = Tenant::factory()->create(['code' => 'MAP-READY', 'status' => \App\Enums\TenantStatus::PROVISIONING]);
    $cm = User::factory()->create(['tenant_id' => $tenant->id]);
    $cm->assignRole('Campus Manager');

    $hostel = \App\Models\Hostel::factory()->create([
        'tenant_id' => $tenant->id,
        'curfew_time' => '22:00:00',
    ]);
    // Rooms/beds
    $room = \App\Models\Room::factory()->create([
        'tenant_id' => $tenant->id,
        'campus_id' => $hostel->campus_id,
        'hostel_id' => $hostel->id,
    ]);
    \App\Models\RoomBed::factory()->create([
        'tenant_id' => $tenant->id,
        'room_id' => $room->id,
        'hostel_id' => $hostel->id,
    ]);

    // Rector + contacts
    $rector = User::factory()->create(['tenant_id' => $tenant->id, 'phone' => '+911234567890']);
    $rector->assignRole('Rector');
    $college = User::factory()->create(['tenant_id' => $tenant->id, 'phone' => '+911111111111']);
    $college->assignRole('College Management');

    $service = app(PreflightService::class);
    $result = $service->evaluate($tenant, [
        'hostels' => [
            [
                'id' => $hostel->id,
                'roles_na' => [],
            ],
        ],
        'staff' => [
            'campus_manager_id' => $cm->id,
        ],
        'contacts' => [
            'rector_phone' => $rector->phone,
            'college_mgmt_phone' => $college->phone,
        ],
    ]);

    expect($result['passed'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

