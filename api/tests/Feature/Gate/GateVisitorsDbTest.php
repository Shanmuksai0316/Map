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

class GateVisitorsDbTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Guard', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'sanctum']);
    }

    public function test_guard_gets_visitors_from_db_within_window(): void
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

        // Create two guest visits for today
        GuestVisit::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'name' => 'Visitor One',
            'phone' => '1234567890',
            'whom_to_meet' => 'Student',
            'visit_date' => today(),
            'status' => GuestVisit::STATUS_PRE_REGISTERED,
            'created_by_user_id' => $guard->id,
        ]);

        GuestVisit::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'name' => 'Visitor Two',
            'phone' => '1234567891',
            'whom_to_meet' => 'Friend',
            'visit_date' => today(),
            'status' => GuestVisit::STATUS_PRE_REGISTERED,
            'created_by_user_id' => $guard->id,
        ]);

        // Set time to within visiting hours (17:00)
        Carbon::setTestNow(Carbon::create(2025, 10, 1, 17, 0, 0, 'Asia/Kolkata'));

        Sanctum::actingAs($guard, ['*']);

        $response = $this->getJson("/api/v1/gate/visitors/today?hostel_id={$hostel->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'visitors')
            ->assertJsonFragment([
                'name' => 'Visitor One',
                'within_window' => true,
            ])
            ->assertJsonFragment([
                'name' => 'Visitor Two',
                'within_window' => true,
            ]);
    }

    public function test_guard_sees_within_window_false_outside_hours(): void
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

        GuestVisit::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'name' => 'Late Visitor',
            'phone' => '1234567890',
            'whom_to_meet' => 'Student',
            'visit_date' => today(),
            'status' => GuestVisit::STATUS_PRE_REGISTERED,
            'created_by_user_id' => $guard->id,
        ]);

        // Set time to outside visiting hours (20:00)
        Carbon::setTestNow(Carbon::create(2025, 10, 1, 20, 0, 0, 'Asia/Kolkata'));

        Sanctum::actingAs($guard, ['*']);

        $response = $this->getJson("/api/v1/gate/visitors/today?hostel_id={$hostel->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Late Visitor',
                'within_window' => false,
            ]);
    }

    public function test_allow_visitor_updates_guest_visit_status(): void
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

        $visit = GuestVisit::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'name' => 'Allowed Visitor',
            'phone' => '1234567890',
            'whom_to_meet' => 'Student',
            'visit_date' => today(),
            'status' => GuestVisit::STATUS_PRE_REGISTERED,
            'created_by_user_id' => $guard->id,
        ]);

        // Set time to within visiting hours
        Carbon::setTestNow(Carbon::create(2025, 10, 1, 17, 0, 0, 'Asia/Kolkata'));

        Sanctum::actingAs($guard, ['*']);

        $response = $this->postJson("/api/v1/gate/visitors/{$visit->id}/allow", [
            'hostel_id' => $hostel->id,
            'method' => 'manual',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('guest_visits', [
            'id' => $visit->id,
            'status' => 'allowed',
            'allowed_by_user_id' => $guard->id,
        ]);
    }

    public function test_deny_visitor_updates_guest_visit_status(): void
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

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
        ]);

        $visit = GuestVisit::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'name' => 'Denied Visitor',
            'phone' => '1234567890',
            'whom_to_meet' => 'Student',
            'visit_date' => today(),
            'status' => GuestVisit::STATUS_PRE_REGISTERED,
            'created_by_user_id' => $guard->id,
        ]);

        Sanctum::actingAs($guard, ['*']);

        $response = $this->postJson("/api/v1/gate/visitors/{$visit->id}/deny", [
            'hostel_id' => $hostel->id,
            'note' => 'Not on pre-registration list',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('guest_visits', [
            'id' => $visit->id,
            'status' => 'denied',
            'denied_by_user_id' => $guard->id,
        ]);
    }

    public function test_allow_with_device_flag_on_requires_device_header(): void
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

        // Set time to within hours
        Carbon::setTestNow(Carbon::create(2025, 10, 1, 17, 0, 0, 'Asia/Kolkata'));

        Sanctum::actingAs($guard, ['*']);

        // Call without device header
        $response = $this->postJson("/api/v1/gate/visitors/{$visit->id}/allow", [
            'hostel_id' => $hostel->id,
            'method' => 'manual',
        ]);

        $response->assertStatus(403);
    }
}

