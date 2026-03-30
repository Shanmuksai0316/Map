<?php

namespace Tests\Feature\MobileP0;

use App\Domain\Gate\Models\GateEntry;
use App\Enums\OutPassStatus;
use App\Enums\OutPassType;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GateLateReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_guard_gate_in_detects_late_return(): void
    {
        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        
        // Create a tenant first
        $tenant = \App\Models\Tenant::factory()->create();
        
        // Create a guard user
        $guard = User::factory()->create([
            'kind' => 'Guard',
            'tenant_id' => $tenant->id,
        ]);
        
        // Assign Guard role
        $guard->assignRole('Guard');

        // Create a student
        $student = User::factory()->create([
            'kind' => 'Student',
            'tenant_id' => $tenant->id,
        ]);

        // Create a hostel first
        $hostel = \App\Models\Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        
        $studentRecord = $student->student()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_uid' => 'TEST-' . rand(1000, 9999),
            'map_student_id' => 'MAP-' . rand(10000, 99999),
        ]);

        // Create an expired out-pass (valid_until is 1 hour ago)
        $outpass = OutPass::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $studentRecord->id,
            'requested_at' => now('Asia/Kolkata')->subHours(2),
            'valid_until' => now('Asia/Kolkata')->subHour(), // 1 hour ago
            'status' => OutPassStatus::APPROVED,
            'reason' => OutPassType::NORMAL,
            'decision_by' => $guard->id,
            'decided_at' => now('Asia/Kolkata'),
        ]);

        // Create a Gate OUT entry for the student
        $gateOut = GateEntry::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $studentRecord->id,
            'outpass_id' => $outpass->id,
            'event' => 'student_exit',
            'occurred_at' => now('Asia/Kolkata'),
            'source' => 'web',
            'direction' => GateEntry::DIRECTION_OUT,
            'method' => 'manual',
            'verified' => false,
            'guard_user_id' => $guard->id,
            'guard_id' => $guard->id,
        ]);

        // Authenticate as guard
        Sanctum::actingAs($guard);

        // Perform Gate IN
        $response = $this->postJson('/api/v1/gate/in', [
            'student_id' => $student->id, // user_id, not student_id
            'method' => 'manual',
            'note' => 'Testing late return detection',
        ]);

        // Assert response
        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'late_minutes',
            ]);
            
        // Assert late_minutes is positive
        $lateMinutes = $response->json('late_minutes');
        $this->assertGreaterThan(0, $lateMinutes, 'Late minutes should be positive when student returns after valid_until time');
        
        // Verify the late minutes are reasonable (should be around 60 minutes since valid_until was 1 hour ago)
        $this->assertGreaterThanOrEqual(55, $lateMinutes, 'Late minutes should be at least 55 minutes');
        $this->assertLessThanOrEqual(65, $lateMinutes, 'Late minutes should be at most 65 minutes');
    }

    public function test_guard_gate_in_no_late_when_on_time(): void
    {
        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        
        // Create a tenant first
        $tenant = \App\Models\Tenant::factory()->create();
        
        // Create a guard user
        $guard = User::factory()->create([
            'kind' => 'Guard',
            'tenant_id' => $tenant->id,
        ]);
        
        // Assign Guard role
        $guard->assignRole('Guard');

        // Create a student
        $student = User::factory()->create([
            'kind' => 'Student',
            'tenant_id' => $tenant->id,
        ]);

        // Create a hostel first
        $hostel = \App\Models\Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        
        $studentRecord = $student->student()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_uid' => 'TEST-' . rand(1000, 9999),
            'map_student_id' => 'MAP-' . rand(10000, 99999),
        ]);

        // Create a future out-pass (valid_until is 1 hour from now)
        $outpass = OutPass::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $studentRecord->id,
            'requested_at' => now('Asia/Kolkata'),
            'valid_until' => now('Asia/Kolkata')->addHour(), // 1 hour from now
            'status' => OutPassStatus::APPROVED,
            'reason' => OutPassType::NORMAL,
            'decision_by' => $guard->id,
            'decided_at' => now('Asia/Kolkata'),
        ]);

        // Create a Gate OUT entry for the student
        $gateOut = GateEntry::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $studentRecord->id,
            'outpass_id' => $outpass->id,
            'event' => 'student_exit',
            'occurred_at' => now('Asia/Kolkata'),
            'source' => 'web',
            'direction' => GateEntry::DIRECTION_OUT,
            'method' => 'manual',
            'verified' => false,
            'guard_user_id' => $guard->id,
            'guard_id' => $guard->id,
        ]);

        // Authenticate as guard
        Sanctum::actingAs($guard);

        // Perform Gate IN
        $response = $this->postJson('/api/v1/gate/in', [
            'student_id' => $student->id, // user_id, not student_id
            'method' => 'manual',
            'note' => 'Testing on-time return',
        ]);

        // Assert response
        $response->assertStatus(201)
            ->assertJson([
                'late_minutes' => 0, // Should be 0 when returning on time
            ]);
    }
}
