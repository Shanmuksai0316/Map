<?php

namespace Tests\Feature\Gate;

use App\Domain\Gate\Models\GateDevice;
use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GateHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Guard', 'guard_name' => 'sanctum']);
    }

    public function test_heartbeat_updates_last_seen_at(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $guard = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Guard',
        ]);
        $guard->assignRole('Guard');

        // Create device with old last_seen_at
        $device = GateDevice::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'device_uuid' => 'TEST-DEVICE-UUID',
            'name' => 'Test Device',
            'is_active' => true,
            'enrolled_by_user_id' => $guard->id,
            'enrolled_at' => now(),
            'last_seen_at' => Carbon::now()->subHours(2),
        ]);

        $oldLastSeenAt = $device->last_seen_at;

        Sanctum::actingAs($guard, ['*']);

        $response = $this->postJson('/api/v1/gate/devices/heartbeat', [
            'device_uuid' => 'TEST-DEVICE-UUID',
            'hostel_id' => $hostel->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['ok', 'last_seen_at', 'hostel_id'])
            ->assertJson([
                'ok' => true,
                'hostel_id' => $hostel->id,
            ]);

        $device->refresh();
        $this->assertTrue($device->last_seen_at->greaterThan($oldLastSeenAt));
    }
}

