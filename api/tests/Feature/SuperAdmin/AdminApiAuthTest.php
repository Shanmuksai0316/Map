<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Campus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminApiAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Role::findOrCreate('Super Admin', 'web');
        
        $this->tenant = Tenant::factory()->create();
        $this->superAdmin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->superAdmin->assignRole('Super Admin');
        
        $this->campus = Campus::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_admin_api_routes_require_authentication(): void
    {
        // Test without authentication - should fail
        $response = $this->getJson('/api/v1/admin/campuses');
        $response->assertStatus(401); // Should require authentication
    }

    public function test_super_admin_can_access_admin_api_with_auth(): void
    {
        // Test with Super Admin authentication
        $token = $this->superAdmin->createToken('test-token');
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken,
        ])->getJson('/api/v1/admin/campuses');
        
        $response->assertStatus(200); // Should work with auth
    }

    public function test_non_super_admin_cannot_access_admin_api(): void
    {
        // Create regular user (not Super Admin)
        $regularUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $token = $regularUser->createToken('test-token');
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken,
        ])->getJson('/api/v1/admin/campuses');
        
        $response->assertStatus(403); // Should be forbidden
    }
}




