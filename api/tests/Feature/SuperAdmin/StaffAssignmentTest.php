<?php

namespace Tests\Feature\SuperAdmin;

use App\Events\StaffAssignmentChanged;
use App\Events\UserRoleChanged;
use App\Models\Hostel;
use App\Models\StaffUser;
use App\Models\Tenant;
use App\Models\User;
use App\Support\HostelScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Warden', 'web');
        Role::findOrCreate('Guard', 'web');
    }

    public function test_super_admin_can_create_staff_user_with_hostel_assignments(): void
    {
        Event::fake();
        
        $tenant = Tenant::factory()->create();
        $superAdmin = User::factory()->create(['tenant_id' => $tenant->id]);
        $superAdmin->assignRole('Super Admin');
        
        $hostel1 = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        
        $this->actingAs($superAdmin);
        
        $response = $this->postJson('/api/v1/staff-users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'password' => 'password123',
            'role_hint' => 'Warden',
            'hostels' => [$hostel1->id],
        ]);
        
        $response->assertStatus(201);
        
        $staffUser = StaffUser::where('email', 'john@example.com')->first();
        $this->assertNotNull($staffUser);
        $this->assertTrue($staffUser->hasRole('Warden'));
        
        $assignedHostels = $staffUser->staffHostels()->pluck('hostels.id')->toArray();
        $this->assertEquals([$hostel1->id], $assignedHostels);
        
        Event::assertDispatched(UserRoleChanged::class);
        Event::assertDispatched(StaffAssignmentChanged::class);
    }

    public function test_super_admin_can_update_staff_assignments(): void
    {
        Event::fake();
        
        $tenant = Tenant::factory()->create();
        $superAdmin = User::factory()->create(['tenant_id' => $tenant->id]);
        $superAdmin->assignRole('Super Admin');
        
        $staffUser = User::factory()->create(['tenant_id' => $tenant->id, 'kind' => 'staff']);
        $staffUser->assignRole('Warden');
        
        $hostel1 = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        $hostel2 = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        
        // Assign initial hostels
        $staffUser->staffHostels()->attach([$hostel1->id], [
            'tenant_id' => $tenant->id,
            'assigned_at' => now(),
        ]);
        
        $this->actingAs($superAdmin);
        
        $response = $this->putJson("/api/v1/staff-users/{$staffUser->id}", [
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'role_hint' => 'Guard',
            'hostels' => [$hostel2->id],
        ]);
        
        $response->assertStatus(200);
        
        $staffUser->refresh();
        $this->assertTrue($staffUser->hasRole('Guard'));
        
        $assignedHostels = $staffUser->staffHostels()->pluck('hostels.id')->toArray();
        $this->assertEquals([$hostel2->id], $assignedHostels);
        
        Event::assertDispatched(UserRoleChanged::class);
        Event::assertDispatched(StaffAssignmentChanged::class);
    }

    public function test_hostel_scope_returns_assigned_hostels(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $hostel1 = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        
        // Assign user to specific hostels
        $user->staffHostels()->attach([$hostel1->id], [
            'tenant_id' => $tenant->id,
            'assigned_at' => now(),
        ]);
        
        $hostelIds = HostelScope::idsFor($user);
        $this->assertEquals([$hostel1->id], $hostelIds);
    }

    public function test_hostel_scope_returns_all_tenant_hostels_when_no_assignments(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $hostel1 = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        $hostel2 = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        
        $hostelIds = HostelScope::idsFor($user);
        $this->assertEquals([$hostel1->id, $hostel2->id], $hostelIds);
    }

    public function test_staff_user_tokens_revoked_on_role_change(): void
    {
        $tenant = Tenant::factory()->create();
        $superAdmin = User::factory()->create(['tenant_id' => $tenant->id]);
        $superAdmin->assignRole('Super Admin');
        
        $staffUser = User::factory()->create(['tenant_id' => $tenant->id, 'kind' => 'staff']);
        $staffUser->assignRole('Warden');
        
        // Create some tokens
        $token1 = $staffUser->createToken('test1');
        $token2 = $staffUser->createToken('test2');
        
        $this->assertEquals(2, $staffUser->tokens()->count());
        
        $this->actingAs($superAdmin);
        
        $response = $this->putJson("/api/v1/staff-users/{$staffUser->id}", [
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'role_hint' => 'Guard',
            'hostels' => [],
        ]);
        
        $response->assertStatus(200);
        
        $staffUser->refresh();
        $this->assertEquals(0, $staffUser->tokens()->count());
    }
}
