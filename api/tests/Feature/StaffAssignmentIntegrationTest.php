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

class StaffAssignmentIntegrationTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_database_schema_has_staff_assignments_table(): void
    {
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('staff_assignments'));
    }

    public function test_staff_assignments_table_has_correct_columns(): void
    {
        $columns = DB::getSchemaBuilder()->getColumnListing('staff_assignments');
        
        $expectedColumns = [
            'id', 'tenant_id', 'user_id', 'hostel_id',
            'assigned_at', 'assigned_by', 'assignment_notes',
            'revoked_at', 'revocation_reason', 'revoked_by',
            'created_at', 'updated_at'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columns, "Column {$column} should exist in staff_assignments table");
        }
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

    public function test_api_auth_me_includes_staff_assignment(): void
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

    public function test_assignment_creates_audit_trail(): void
    {
        $this->service->assignStaff($this->staff, [
            'tenant_id' => $this->tenant1->id,
            'hostel_id' => $this->hostel1->id,
            'role' => 'Warden',
            'notes' => 'Test assignment',
        ]);

        $assignment = DB::table('staff_assignments')
            ->where('user_id', $this->staff->id)
            ->whereNull('revoked_at')
            ->first();

        $this->assertNotNull($assignment->assigned_at);
        $this->assertNotNull($assignment->assigned_by);
        $this->assertEquals('Test assignment', $assignment->assignment_notes);
    }

    public function test_revocation_records_reason(): void
    {
        // Assign staff first
        $this->service->assignStaff($this->staff, [
            'tenant_id' => $this->tenant1->id,
            'hostel_id' => $this->hostel1->id,
            'role' => 'Warden',
        ]);

        // Revoke assignment
        $this->service->revokeAssignment($this->staff, 'On medical leave');

        $assignment = DB::table('staff_assignments')
            ->where('user_id', $this->staff->id)
            ->first();

        $this->assertNotNull($assignment->revoked_at);
        $this->assertEquals('On medical leave', $assignment->revocation_reason);
        $this->assertNotNull($assignment->revoked_by);
    }

    public function test_assignment_history_preserved(): void
    {
        // Create multiple assignments
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
        $this->assertNotNull($history[1]->revoked_at); // First assignment revoked
        $this->assertNull($history[0]->revoked_at); // Current assignment active
    }

    public function test_notification_sent_on_cross_tenant_assignment(): void
    {
        Notification::fake();

        // Initial assignment
        $this->service->assignStaff($this->staff, [
            'tenant_id' => $this->tenant1->id,
            'hostel_id' => $this->hostel1->id,
            'role' => 'Warden',
        ]);

        Notification::assertSentTo($this->staff, StaffReassignedNotification::class);

        // Cross-tenant reassignment
        $this->service->assignStaff($this->staff, [
            'tenant_id' => $this->tenant2->id,
            'hostel_id' => $this->hostel2->id,
            'role' => 'Campus Manager',
        ]);

        Notification::assertSentTo(
            $this->staff,
            StaffReassignedNotification::class,
            function ($notification) {
                return $notification->isCrossTenant === true;
            }
        );
    }
}

