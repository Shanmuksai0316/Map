<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_admin_panel_access()
    {
        // Create test data
        $tenant = Tenant::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create(['tenant_id' => $tenant->id]);
        
        // Assign Super Admin role
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdmin->assignRole($superAdminRole);
        
        // Test access
        $response = $this->actingAs($superAdmin, 'web')->get('/admin');
        
        echo "Response status: " . $response->getStatusCode() . PHP_EOL;
        echo "User roles: " . $superAdmin->roles->pluck('name')->join(', ') . PHP_EOL;
        echo "Has Super Admin role: " . ($superAdmin->hasRole('Super Admin') ? 'Yes' : 'No') . PHP_EOL;
        
        if ($response->getStatusCode() !== 200) {
            echo "Response content: " . $response->getContent() . PHP_EOL;
        }
        
        $this->assertTrue(true); // Just for debugging
    }
}
