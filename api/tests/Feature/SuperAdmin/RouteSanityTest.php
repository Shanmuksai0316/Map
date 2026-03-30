<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RouteSanityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $tenant = Tenant::factory()->create();
        $this->superAdmin = User::factory()->superAdmin()->create(['tenant_id' => $tenant->id]);
        $this->regularUser = User::factory()->guard()->create(['tenant_id' => $tenant->id]);
        
        // Assign roles
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $guardRole = Role::firstOrCreate(['name' => 'Guard']);
        
        $this->superAdmin->assignRole($superAdminRole);
        $this->regularUser->assignRole($guardRole);
    }

    public function test_admin_panel_reachable_for_super_admin()
    {
        $this->actingAs($this->superAdmin, 'web')
            ->get('/admin')
            ->assertStatus(200);
    }

    public function test_admin_panel_forbidden_for_non_admin()
    {
        $this->actingAs($this->regularUser, 'web')
            ->get('/admin')
            ->assertStatus(403);
    }

    public function test_staff_users_resource_accessible_to_super_admin()
    {
        $this->actingAs($this->superAdmin, 'web')
            ->get('/admin/staff-users')
            ->assertStatus(200);
    }

    public function test_super_admin_dashboard_accessible()
    {
        $this->actingAs($this->superAdmin, 'web')
            ->get('/admin/super-admin-dashboard')
            ->assertStatus(200);
    }

    public function test_reports_page_accessible_to_super_admin()
    {
        $this->actingAs($this->superAdmin, 'web')
            ->get('/admin/reports')
            ->assertStatus(200);
    }
}
