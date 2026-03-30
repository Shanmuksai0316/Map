<?php

namespace Tests\Feature\Gate;

use App\Domain\Gate\Models\GateDevice;
use App\Domain\Visitors\Models\GuestVisit;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GateVisitorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Guard', 'guard_name' => 'sanctum']);
    }

    public function test_guard_can_list_visitors_within_window(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'visiting_start' => '16:00:00',
            'visiting_end' => '19:00:00',
        ]);

        $guard = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Guard',
        ]);
        $guard->assignRole('Guard');

        // Set time to within visiting hours (17:00)
        Carbon::setTestNow(Carbon::create(2025, 10, 1, 17, 0, 0, 'Asia/Kolkata'));

        Sanctum::actingAs($guard, ['*']);

        $response = $this->getJson('/api/v1/gate/visitors/today?hostel_id=' . $hostel->id);

        $response->assertOk()
            ->assertJsonStructure(['visitors', 'window'])
            ->assertJson([
                'window' => [
                    'within_window' => true,
                ],
            ]);
    }

    public function test_guard_can_list_visitors_outside_window(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'visiting_start' => '16:00:00',
            'visiting_end' => '19:00:00',
        ]);

        $guard = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Guard',
        ]);
        $guard->assignRole('Guard');

        // Set time to outside visiting hours (20:00)
        Carbon::setTestNow(Carbon::create(2025, 10, 1, 20, 0, 0, 'Asia/Kolkata'));

        Sanctum::actingAs($guard, ['*']);

        $response = $this->getJson('/api/v1/gate/visitors/today?hostel_id=' . $hostel->id);

        $response->assertOk()
            ->assertJson([
                'window' => [
                    'within_window' => false,
                ],
            ]);
    }

    public function test_allow_visitor_within_window_succeeds(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'visiting_start' => '16:00:00',
            'visiting_end' => '19:00:00',
        ]);

        $guard = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Guard',
        ]);
        $guard->assignRole('Guard');

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
        ]);

        // Create guest visit
        $visit = GuestVisit::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'name' => 'Test Visitor',
            'phone' => '1234567890',
            'whom_to_meet' => 'Student',
            'visit_date' => today(),
            'status' => GuestVisit::STATUS_PRE_REGISTERED,
            'created_by_user_id' => $guard->id,
        ]);

        // Set time to within visiting hours (17:00)
        Carbon::setTestNow(Carbon::create(2025, 10, 1, 17, 0, 0, 'Asia/Kolkata'));

        Sanctum::actingAs($guard, ['*']);

        $response = $this->postJson("/api/v1/gate/visitors/{$visit->id}/allow", [
            'hostel_id' => $hostel->id,
            'method' => 'manual',
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_allow_visitor_outside_window_fails(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'visiting_start' => '16:00:00',
            'visiting_end' => '19:00:00',
        ]);

        $guard = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Guard',
        ]);
        $guard->assignRole('Guard');

        // Set time to outside visiting hours (20:00)
        Carbon::setTestNow(Carbon::create(2025, 10, 1, 20, 0, 0, 'Asia/Kolkata'));

        Sanctum::actingAs($guard, ['*']);

        $response = $this->postJson('/api/v1/gate/visitors/123/allow', [
            'hostel_id' => $hostel->id,
            'method' => 'manual',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['window']);
    }

    public function test_deny_visitor_succeeds_anytime(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'visiting_start' => '16:00:00',
            'visiting_end' => '19:00:00',
        ]);

        $guard = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Guard',
        ]);
        $guard->assignRole('Guard');

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
        ]);

        // Create guest visit
        $visit = GuestVisit::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'name' => 'Deny Test',
            'phone' => '1234567890',
            'whom_to_meet' => 'Student',
            'visit_date' => today(),
            'status' => GuestVisit::STATUS_PRE_REGISTERED,
            'created_by_user_id' => $guard->id,
        ]);

        // Set time to outside visiting hours (20:00) - deny should still work
        Carbon::setTestNow(Carbon::create(2025, 10, 1, 20, 0, 0, 'Asia/Kolkata'));

        Sanctum::actingAs($guard, ['*']);

        $response = $this->postJson("/api/v1/gate/visitors/{$visit->id}/deny", [
            'hostel_id' => $hostel->id,
            'note' => 'Not on pre-registration list',
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_allow_visitor_without_device_when_flag_on_fails(): void
    {
        config(['features.gate_device_enforcement' => true]);

        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'visiting_start' => '16:00:00',
            'visiting_end' => '19:00:00',
        ]);

        $guard = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Guard',
        ]);
        $guard->assignRole('Guard');

        // Register a device, but don't send UUID in request
        GateDevice::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'device_uuid' => 'REGISTERED-DEVICE',
            'name' => 'Test Device',
            'is_active' => true,
            'enrolled_by_user_id' => $guard->id,
            'enrolled_at' => now(),
        ]);

        // Set time to within visiting hours
        Carbon::setTestNow(Carbon::create(2025, 10, 1, 17, 0, 0, 'Asia/Kolkata'));

        Sanctum::actingAs($guard, ['*']);

        // Call allow without device header/param
        $response = $this->postJson('/api/v1/gate/visitors/123/allow', [
            'hostel_id' => $hostel->id,
            'method' => 'manual',
        ]);

        $response->assertStatus(403);
    }

    public function test_allow_visitor_with_device_when_flag_on_succeeds(): void
    {
        config(['features.gate_device_enforcement' => true]);

        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'visiting_start' => '16:00:00',
            'visiting_end' => '19:00:00',
        ]);

        $guard = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Guard',
        ]);
        $guard->assignRole('Guard');

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
        ]);

        // Create guest visit
        $visit = GuestVisit::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'name' => 'Device Test Visitor',
            'phone' => '1234567890',
            'whom_to_meet' => 'Student',
            'visit_date' => today(),
            'status' => GuestVisit::STATUS_PRE_REGISTERED,
            'created_by_user_id' => $guard->id,
        ]);

        // Register a device
        GateDevice::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'device_uuid' => 'REGISTERED-DEVICE',
            'name' => 'Test Device',
            'is_active' => true,
            'enrolled_by_user_id' => $guard->id,
            'enrolled_at' => now(),
        ]);

        // Set time to within visiting hours
        Carbon::setTestNow(Carbon::create(2025, 10, 1, 17, 0, 0, 'Asia/Kolkata'));

        Sanctum::actingAs($guard, ['*']);

        // Call allow with device UUID (via query param for testing)
        $response = $this->postJson("/api/v1/gate/visitors/{$visit->id}/allow?device_uuid=REGISTERED-DEVICE", [
            'hostel_id' => $hostel->id,
            'method' => 'manual',
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true]);
    }
}

