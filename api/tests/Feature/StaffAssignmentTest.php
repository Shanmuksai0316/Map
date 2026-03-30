<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Campus;
use App\Models\Hostel;
use App\Services\StaffAssignmentService;
use App\Notifications\StaffReassignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seedTestBootstrap = false;
    protected bool $ensureDefaultTestingTenant = false;

    protected StaffAssignmentService $service;
    protected Tenant $tenant1;
    protected Tenant $tenant2;
    protected Hostel $hostel1;
    protected Hostel $hostel2;
    protected User $staff;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.defaults.guard' => 'web']);

        $this->service = app(StaffAssignmentService::class);

        // Create roles
        Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Warden', 'guard_name' => 'web']);
        Role::create(['name' => 'Campus Manager', 'guard_name' => 'web']);

        // Create super admin
        $this->superAdmin = User::factory()->create([
            'kind' => 'staff',
            'email' => 'admin@example.com',
        ]);
        $this->superAdmin->assignRole('Super Admin');

        // Create two tenants
        $this->tenant1 = Tenant::create([
            'name' => 'MIT',
            'code' => 'MIT',
            'status' => 'active',
        ]);

        $this->tenant2 = Tenant::create([
            'name' => 'IIT',
            'code' => 'IIT',
            'status' => 'active',
        ]);

        // Create campuses (required for hostels)
        $campus1 = Campus::create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Main Campus',
            'code' => 'MAIN',
        ]);

        $campus2 = Campus::create([
            'tenant_id' => $this->tenant2->id,
            'name' => 'Main Campus',
            'code' => 'MAIN',
        ]);

        // Create hostels
        $this->hostel1 = Hostel::create([
            'tenant_id' => $this->tenant1->id,
            'campus_id' => $campus1->id,
            'code' => 'BH1',
            'name' => 'Boys Hostel 1',
            'gender_mode' => 'male',
        ]);

        $this->hostel2 = Hostel::create([
            'tenant_id' => $this->tenant2->id,
            'campus_id' => $campus2->id,
            'code' => 'GH1',
            'name' => 'Girls Hostel 1',
            'gender_mode' => 'female',
        ]);

        // Create staff user (MAP staff requires is_map_staff = true)
        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'kind' => 'staff',
            'name' => 'John Warden',
            'email' => 'john@example.com',
            'phone' => '+919876543210',
            'is_map_staff' => true, // MAP staff flag
        ]);
        $this->staff->assignRole('Warden');

        // Authenticate as super admin for tests
        $this->actingAs($this->superAdmin);
    }

    public function test_can_assign_staff_to_hostel(): void
    {
        Notification::fake();

        $this->service->assignStaff($this->staff, [
            'tenant_id' => $this->tenant1->id,
            'hostel_id' => $this->hostel1->id,
            'role' => 'Warden',
            'notes' => 'Initial assignment',
        ]);

        // Check assignment was created
        $assignment = DB::table('staff_assignments')
            ->where('user_id', $this->staff->id)
            ->whereNull('revoked_at')
            ->first();

        $this->assertNotNull($assignment);
        $this->assertEquals($this->hostel1->id, $assignment->hostel_id);
        $this->assertEquals($this->tenant1->id, $assignment->tenant_id);
        $this->assertEquals('Initial assignment', $assignment->assignment_notes);

        // Check role was assigned
        $this->staff->refresh();
        $this->assertTrue($this->staff->hasRole('Warden'));

        // Check notification was sent
        Notification::assertSentTo($this->staff, StaffReassignedNotification::class);
    }

    public function test_can_reassign_staff_within_same_tenant(): void
    {
        // First assignment
        $this->service->assignStaff($this->staff, [
            'tenant_id' => $this->tenant1->id,
            'hostel_id' => $this->hostel1->id,
            'role' => 'Warden',
        ]);

        // Create another hostel in same tenant
        $campus = Campus::first();
        $hostel2 = Hostel::create([
            'tenant_id' => $this->tenant1->id,
            'campus_id' => $campus->id,
            'code' => 'BH2',
            'name' => 'Boys Hostel 2',
            'gender_mode' => 'male',
        ]);

        // Reassign to different hostel
        $this->service->assignStaff($this->staff, [
            'tenant_id' => $this->tenant1->id,
            'hostel_id' => $hostel2->id,
            'role' => 'Warden',
            'notes' => 'Transferred',
        ]);

        // Check old assignment was revoked
        $oldAssignment = DB::table('staff_assignments')
            ->where('user_id', $this->staff->id)
            ->where('hostel_id', $this->hostel1->id)
            ->first();

        $this->assertNotNull($oldAssignment->revoked_at);
        $this->assertStringContainsString('Reassigned', $oldAssignment->revocation_reason);

        // Check new assignment exists
        $newAssignment = DB::table('staff_assignments')
            ->where('user_id', $this->staff->id)
            ->where('hostel_id', $hostel2->id)
            ->whereNull('revoked_at')
            ->first();

        $this->assertNotNull($newAssignment);
        $this->assertEquals('Transferred', $newAssignment->assignment_notes);
    }

    public function test_can_reassign_staff_across_tenants(): void
    {
        // Assign to tenant1
        $this->service->assignStaff($this->staff, [
            'tenant_id' => $this->tenant1->id,
            'hostel_id' => $this->hostel1->id,
            'role' => 'Warden',
        ]);

        // Reassign to tenant2 (cross-tenant)
        $this->service->assignStaff($this->staff, [
            'tenant_id' => $this->tenant2->id,
            'hostel_id' => $this->hostel2->id,
            'role' => 'Campus Manager',
            'notes' => 'Promoted and transferred',
        ]);

        // Check staff's tenant_id was updated
        $this->staff->refresh();
        $this->assertEquals($this->tenant2->id, $this->staff->tenant_id);

        // Check role was changed
        $this->assertTrue($this->staff->hasRole('Campus Manager'));
        $this->assertFalse($this->staff->hasRole('Warden'));

        // Check old assignment was revoked
        $oldAssignment = DB::table('staff_assignments')
            ->where('user_id', $this->staff->id)
            ->where('tenant_id', $this->tenant1->id)
            ->first();

        $this->assertNotNull($oldAssignment->revoked_at);
        $this->assertStringContainsString('Cross-tenant', $oldAssignment->revocation_reason);

        // Check new assignment exists
        $newAssignment = DB::table('staff_assignments')
            ->where('user_id', $this->staff->id)
            ->where('tenant_id', $this->tenant2->id)
            ->whereNull('revoked_at')
            ->first();

        $this->assertNotNull($newAssignment);
        $this->assertEquals($this->hostel2->id, $newAssignment->hostel_id);
    }

    public function test_staff_can_only_have_one_active_assignment(): void
    {
        // Assign staff
        $this->service->assignStaff($this->staff, [
            'tenant_id' => $this->tenant1->id,
            'hostel_id' => $this->hostel1->id,
            'role' => 'Warden',
        ]);

        // Try to manually insert another active assignment (should fail due to unique constraint)
        $this->expectException(\Exception::class);

        DB::table('staff_assignments')->insert([
            'user_id' => $this->staff->id,
            'tenant_id' => $this->tenant2->id,
            'hostel_id' => $this->hostel2->id,
            'assigned_at' => now(),
            'assigned_by' => $this->superAdmin->id,
            'created_at' => now(),
            'updated_at' => now(),
            'revoked_at' => null, // Active
        ]);
    }

    public function test_cannot_assign_rector_via_staff_assignment_service(): void
    {
        // Create Rector (college representative, not MAP staff)
        $rector = User::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'kind' => 'Rector',
            'name' => 'College Rector',
            'email' => 'rector@example.com',
            'phone' => '+919876543211',
            'is_map_staff' => false, // College representative
        ]);
        $rector->assignRole('Rector');

        // Attempting to assign Rector should throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only MAP staff can be assigned to hostels');

        $this->service->assignStaff($rector, [
            'tenant_id' => $this->tenant1->id,
            'hostel_id' => $this->hostel1->id,
            'role' => 'Rector',
        ]);
    }

    public function test_cannot_assign_college_management_via_staff_assignment_service(): void
    {
        // Create College Management (college representative, not MAP staff)
        Role::create(['name' => 'College Management', 'guard_name' => 'web']);
        
        $collegeMgmt = User::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'kind' => 'CollegeManagement',
            'name' => 'College Management',
            'email' => 'collegemgmt@example.com',
            'phone' => '+919876543212',
            'is_map_staff' => false, // College representative
        ]);
        $collegeMgmt->assignRole('College Management');

        // Attempting to assign College Management should throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only MAP staff can be assigned to hostels');

        $this->service->assignStaff($collegeMgmt, [
            'tenant_id' => $this->tenant1->id,
            'hostel_id' => $this->hostel1->id,
            'role' => 'College Management',
        ]);
    }

    public function test_can_revoke_staff_assignment(): void
    {
        // Assign staff
        $this->service->assignStaff($this->staff, [
            'tenant_id' => $this->tenant1->id,
            'hostel_id' => $this->hostel1->id,
            'role' => 'Warden',
        ]);

        // Revoke assignment
        $this->service->revokeAssignment($this->staff, 'On leave for 2 months');

        // Check assignment was revoked
        $assignment = DB::table('staff_assignments')
            ->where('user_id', $this->staff->id)
            ->first();

        $this->assertNotNull($assignment->revoked_at);
        $this->assertEquals('On leave for 2 months', $assignment->revocation_reason);

        // Check no active assignment exists
        $this->assertFalse($this->service->hasActiveAssignment($this->staff));
    }

    public function test_can_get_active_assignment(): void
    {
        $this->service->assignStaff($this->staff, [
            'tenant_id' => $this->tenant1->id,
            'hostel_id' => $this->hostel1->id,
            'role' => 'Warden',
        ]);

        $assignment = $this->service->getActiveAssignment($this->staff);

        $this->assertNotNull($assignment);
        $this->assertEquals($this->hostel1->id, $assignment->hostel_id);
        $this->assertNull($assignment->revoked_at);
    }

    public function test_can_get_assignment_history(): void
    {
        // Multiple assignments
        $this->service->assignStaff($this->staff, [
            'tenant_id' => $this->tenant1->id,
            'hostel_id' => $this->hostel1->id,
            'role' => 'Warden',
        ]);

        sleep(1); // Ensure different timestamps

        $this->service->assignStaff($this->staff, [
            'tenant_id' => $this->tenant2->id,
            'hostel_id' => $this->hostel2->id,
            'role' => 'Campus Manager',
        ]);

        $history = $this->service->getAssignmentHistory($this->staff);

        $this->assertCount(2, $history);
        $this->assertEquals($this->hostel2->id, $history[0]->hostel_id); // Most recent first
        $this->assertEquals($this->hostel1->id, $history[1]->hostel_id);
    }

    public function test_api_returns_assignment_info_for_staff(): void
    {
        $this->service->assignStaff($this->staff, [
            'tenant_id' => $this->tenant1->id,
            'hostel_id' => $this->hostel1->id,
            'role' => 'Warden',
        ]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'phone',
                'kind',
                'staff_assignment' => [
                    'hostel_id',
                    'hostel_name',
                    'assigned_at',
                    'assignment_status',
                ],
                'role',
            ],
        ]);

        $response->assertJson([
            'data' => [
                'staff_assignment' => [
                    'hostel_name' => 'Boys Hostel 1',
                    'assignment_status' => 'active',
                ],
                'role' => 'Warden',
            ],
        ]);
    }

    public function test_api_returns_unassigned_status_for_staff_without_assignment(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'staff_assignment' => [
                    'hostel_id' => null,
                    'hostel_name' => null,
                    'assigned_at' => null,
                    'assignment_status' => 'unassigned',
                ],
            ],
        ]);
    }
}


