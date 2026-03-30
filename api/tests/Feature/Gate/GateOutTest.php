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

class GateOutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Guard', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'sanctum']);
    }

    public function test_guard_can_process_out_with_approved_outpass(): void
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

        // Create approved outpass with start_at within window (now - 60m to now)
        $outpass = OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'status' => 'approved',
            'requested_at' => Carbon::now()->subMinutes(30), // Started 30 mins ago
            'valid_until' => Carbon::now()->addHours(4),
        ]);

        Sanctum::actingAs($guard, ['*']);

        $response = $this->postJson('/api/v1/gate/out', [
            'student_id' => $studentUser->id,
            'method' => 'qr',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'verified', 'outpass_id'])
            ->assertJson([
                'verified' => true,
                'outpass_id' => $outpass->id,
            ]);

        $this->assertDatabaseHas('gate_entries', [
            'student_id' => $student->id,
            'outpass_id' => $outpass->id,
            'direction' => 'out',
            'method' => 'qr',
            'verified' => true,
            'guard_user_id' => $guard->id,
        ]);

        // Note: Audit log checks are skipped as audit_logs table doesn't exist in test DB yet
    }

    public function test_out_fails_without_approved_outpass_for_non_manual_method(): void
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

        $response = $this->postJson('/api/v1/gate/out', [
            'student_id' => $studentUser->id,
            'method' => 'qr',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['outpass'])
            ->assertJson([
                'errors' => [
                    'outpass' => ['E_OUTPASS_REQUIRED'],
                ],
            ]);
    }

    public function test_manual_override_allows_out_without_outpass(): void
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

        $response = $this->postJson('/api/v1/gate/out', [
            'student_id' => $studentUser->id,
            'method' => 'manual',
            'note' => 'Emergency - id mismatch',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'verified' => false,
                'outpass_id' => null,
            ]);

        $this->assertDatabaseHas('gate_entries', [
            'student_id' => $student->id,
            'direction' => 'out',
            'method' => 'manual',
            'verified' => false,
            'note' => 'Emergency - id mismatch',
            'guard_user_id' => $guard->id,
        ]);
    }

    public function test_out_with_student_uid(): void
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
            'student_uid' => 'STU123456',
        ]);

        $outpass = OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'status' => 'approved',
            'requested_at' => Carbon::now()->subMinutes(30),
            'valid_until' => Carbon::now()->addHours(4),
        ]);

        Sanctum::actingAs($guard, ['*']);

        $response = $this->postJson('/api/v1/gate/out', [
            'student_uid' => 'STU123456',
            'method' => 'qr',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'verified' => true,
                'outpass_id' => $outpass->id,
            ]);
    }

    public function test_otp_method_verifies_otp(): void
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

        $outpass = OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'status' => 'approved',
            'requested_at' => Carbon::now()->subMinutes(30),
            'valid_until' => Carbon::now()->addHours(4),
        ]);

        Sanctum::actingAs($guard, ['*']);

        $response = $this->postJson('/api/v1/gate/out', [
            'student_id' => $studentUser->id,
            'method' => 'otp',
            'otp_code' => '123456',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'verified' => true, // OtpVerifier stub returns true
            ]);
    }
}
