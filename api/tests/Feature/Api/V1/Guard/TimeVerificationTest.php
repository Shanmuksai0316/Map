<?php

namespace Tests\Feature\Api\V1\Guard;

use App\Models\User;
use App\Models\Tenant;
use App\Models\OutPass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimeVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $guard;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->guard = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'guard',
        ]);
    }

    public function test_guard_can_record_checkout_time(): void
    {
        Sanctum::actingAs($this->guard);

        $student = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'student',
        ]);

        $outpass = OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $student->id,
            'status' => 'approved',
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addHours(6),
        ]);

        $response = $this->postJson('/api/v1/guard/gate/verify-time', [
            'outpass_id' => $outpass->id,
            'verification_type' => 'out',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Check-out time recorded successfully');

        $this->assertNotNull($outpass->fresh()->actual_out_time);
        $this->assertEquals($this->guard->id, $outpass->fresh()->verified_by_guard_id);
    }

    public function test_guard_can_record_checkin_time(): void
    {
        Sanctum::actingAs($this->guard);

        $student = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'student',
        ]);

        $outpass = OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $student->id,
            'status' => 'approved',
            'valid_from' => now()->subHours(4),
            'valid_until' => now()->addHours(2),
            'actual_out_time' => now()->subHours(3),
        ]);

        $response = $this->postJson('/api/v1/guard/gate/verify-time', [
            'outpass_id' => $outpass->id,
            'verification_type' => 'in',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Check-in time recorded successfully');

        $this->assertNotNull($outpass->fresh()->actual_in_time);
    }

    public function test_cannot_record_checkin_before_checkout(): void
    {
        Sanctum::actingAs($this->guard);

        $student = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'student',
        ]);

        $outpass = OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $student->id,
            'status' => 'approved',
            'actual_out_time' => null,
        ]);

        $response = $this->postJson('/api/v1/guard/gate/verify-time', [
            'outpass_id' => $outpass->id,
            'verification_type' => 'in',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot record check-in without check-out');
    }

    public function test_cannot_verify_unapproved_outpass(): void
    {
        Sanctum::actingAs($this->guard);

        $student = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'student',
        ]);

        $outpass = OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $student->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/v1/guard/gate/verify-time', [
            'outpass_id' => $outpass->id,
            'verification_type' => 'out',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Outpass is not approved');
    }

    public function test_cannot_verify_expired_outpass(): void
    {
        Sanctum::actingAs($this->guard);

        $student = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'student',
        ]);

        $outpass = OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $student->id,
            'status' => 'approved',
            'valid_until' => now()->subHours(2),
        ]);

        $response = $this->postJson('/api/v1/guard/gate/verify-time', [
            'outpass_id' => $outpass->id,
            'verification_type' => 'out',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Outpass has expired');
    }
}

