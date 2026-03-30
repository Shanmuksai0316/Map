<?php

namespace Tests\Feature\Gate;

use App\Domain\Gate\Models\GateDevice;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GateDeviceEnrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Campus Manager', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Guard', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'sanctum']);
    }

    public function test_campus_manager_can_register_device(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $campusManager = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'CampusManager',
        ]);
        $campusManager->assignRole('Campus Manager');

        Sanctum::actingAs($campusManager, ['*']);

        $response = $this->postJson('/api/v1/gate/devices/register', [
            'hostel_id' => $hostel->id,
            'device_uuid' => 'DEVICE-UUID-123',
            'name' => 'North Gate Tablet',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'hostel_id', 'device_uuid', 'is_active'])
            ->assertJson([
                'hostel_id' => $hostel->id,
                'device_uuid' => 'DEVICE-UUID-123',
                'is_active' => true,
            ]);

        $this->assertDatabaseHas('gate_devices', [
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'device_uuid' => 'DEVICE-UUID-123',
            'name' => 'North Gate Tablet',
            'is_active' => true,
            'enrolled_by_user_id' => $campusManager->id,
        ]);
    }

    public function test_guard_with_mismatched_device_uuid_gets_403_when_flag_on(): void
    {
        config(['features.gate_device_enforcement' => true]);

        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $guard = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Guard',
        ]);
        $guard->assignRole('Guard');

        $studentUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'user_id' => $studentUser->id,
        ]);

        // Register a device
        GateDevice::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'device_uuid' => 'CORRECT-UUID',
            'name' => 'Test Device',
            'is_active' => true,
            'enrolled_by_user_id' => $guard->id,
            'enrolled_at' => now(),
        ]);

        // Create approved outpass
        OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'status' => 'approved',
            'requested_at' => Carbon::now()->subMinutes(30),
            'valid_until' => Carbon::now()->addHours(4),
        ]);

        Sanctum::actingAs($guard, ['*']);

        // Call /out with wrong device UUID
        $response = $this->postJson('/api/v1/gate/out?device_uuid=WRONG-UUID', [
            'student_id' => $studentUser->id,
            'method' => 'qr',
        ]);

        $response->assertStatus(403);
    }

    public function test_guard_with_correct_device_uuid_succeeds_when_flag_on(): void
    {
        config(['features.gate_device_enforcement' => true]);

        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $guard = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Guard',
        ]);
        $guard->assignRole('Guard');

        $studentUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'user_id' => $studentUser->id,
        ]);

        // Register a device
        GateDevice::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'device_uuid' => 'CORRECT-UUID',
            'name' => 'Test Device',
            'is_active' => true,
            'enrolled_by_user_id' => $guard->id,
            'enrolled_at' => now(),
        ]);

        // Create approved outpass
        OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'status' => 'approved',
            'requested_at' => Carbon::now()->subMinutes(30),
            'valid_until' => Carbon::now()->addHours(4),
        ]);

        Sanctum::actingAs($guard, ['*']);

        // Call /out with correct device UUID (via query param for testing)
        $response = $this->postJson('/api/v1/gate/out?device_uuid=CORRECT-UUID', [
            'student_id' => $studentUser->id,
            'method' => 'qr',
        ]);

        $response->assertStatus(201);
    }
}

