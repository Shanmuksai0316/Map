<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Role::findOrCreate('Super Admin', 'web');
    }

    public function test_super_admin_can_access_dashboard(): void
    {
        $tenant = Tenant::factory()->create();
        $superAdmin = User::factory()->create(['tenant_id' => $tenant->id]);
        $superAdmin->assignRole('Super Admin');
        
        $this->actingAs($superAdmin);
        
        $response = $this->get('/admin/super-admin-dashboard');
        
        $response->assertStatus(200);
    }

    public function test_non_super_admin_cannot_access_dashboard(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $this->actingAs($user);
        
        $response = $this->get('/admin/super-admin-dashboard');
        
        $response->assertStatus(403);
    }

    public function test_dashboard_respects_feature_flag(): void
    {
        config(['features.super_admin_staff_mgmt' => false]);
        
        $tenant = Tenant::factory()->create();
        $superAdmin = User::factory()->create(['tenant_id' => $tenant->id]);
        $superAdmin->assignRole('Super Admin');
        
        $this->actingAs($superAdmin);
        
        $response = $this->get('/admin/super-admin-dashboard');
        
        $response->assertStatus(403);
    }
}
