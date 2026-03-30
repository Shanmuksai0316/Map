<?php

namespace Tests\Feature\Gate;

use App\Domain\Gate\Models\GateEntry;
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

class GateInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Guard', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'sanctum']);
    }

    public function test_guard_can_process_in_with_late_return(): void
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

        $studentUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'user_id' => $studentUser->id,
        ]);

        // Create approved outpass with return_by = now - 30 minutes (late)
        $outpass = OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'status' => 'approved',
            'requested_at' => Carbon::today()->addHours(10),
            'valid_until' => Carbon::now()->subMinutes(30), // Late by 30 minutes
        ]);

        Sanctum::actingAs($guard, ['*']);

        $response = $this->postJson('/api/v1/gate/in', [
            'student_id' => $studentUser->id,
            'method' => 'qr',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'late_minutes']);

        $lateMinutes = $response->json('late_minutes');
        $this->assertGreaterThanOrEqual(29, $lateMinutes);
        $this->assertLessThanOrEqual(31, $lateMinutes);

        $this->assertDatabaseHas('gate_entries', [
            'student_id' => $student->id,
            'outpass_id' => $outpass->id,
            'direction' => 'in',
            'method' => 'qr',
            'verified' => true,
        ]);

        // Note: Audit log checks are skipped as audit_logs table doesn't exist in test DB yet
    }

    public function test_in_after_manual_out_without_outpass(): void
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

        $studentUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'user_id' => $studentUser->id,
        ]);

        // Create manual OUT entry without outpass
        GateEntry::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'outpass_id' => null,
            'event' => 'student_exit',
            'occurred_at' => now(),
            'source' => 'manual',
            'direction' => 'out',
            'method' => 'manual',
            'verified' => false,
            'guard_user_id' => $guard->id,
            'guard_id' => $guard->id,
            'note' => 'Emergency exit',
        ]);

        Sanctum::actingAs($guard, ['*']);

        $response = $this->postJson('/api/v1/gate/in', [
            'student_id' => $studentUser->id,
            'method' => 'qr',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'late_minutes' => 0, // No outpass, so no late calculation
            ]);

        $this->assertDatabaseHas('gate_entries', [
            'student_id' => $student->id,
            'outpass_id' => null,
            'direction' => 'in',
            'late_minutes' => 0,
        ]);
    }

    public function test_in_on_time_has_zero_late_minutes(): void
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

        $studentUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'user_id' => $studentUser->id,
        ]);

        // Create approved outpass with return_by = now + 1 hour (on time)
        $outpass = OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'status' => 'approved',
            'requested_at' => Carbon::today()->addHours(10),
            'valid_until' => Carbon::now()->addHour(), // Not late
        ]);

        Sanctum::actingAs($guard, ['*']);

        $response = $this->postJson('/api/v1/gate/in', [
            'student_id' => $studentUser->id,
            'method' => 'qr',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'late_minutes' => 0,
            ]);

        // Note: Audit log checks are skipped as audit_logs table doesn't exist in test DB yet
    }

    public function test_manual_in_sets_verified_false(): void
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

        $studentUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'user_id' => $studentUser->id,
        ]);

        Sanctum::actingAs($guard, ['*']);

        $response = $this->postJson('/api/v1/gate/in', [
            'student_id' => $studentUser->id,
            'method' => 'manual',
            'note' => 'Phone died, manual entry',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('gate_entries', [
            'student_id' => $student->id,
            'direction' => 'in',
            'method' => 'manual',
            'verified' => false,
            'note' => 'Phone died, manual entry',
        ]);
    }

    public function test_in_with_student_uid(): void
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

        $studentUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'user_id' => $studentUser->id,
            'student_uid' => 'STU789012',
        ]);

        Sanctum::actingAs($guard, ['*']);

        $response = $this->postJson('/api/v1/gate/in', [
            'student_uid' => 'STU789012',
            'method' => 'qr',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('gate_entries', [
            'student_id' => $student->id,
            'direction' => 'in',
        ]);
    }
}
