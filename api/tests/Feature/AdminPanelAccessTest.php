<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Spatie caches permissions between requests; ensure a clean slate
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        
        // Ensure roles exist for tests
        Role::firstOrCreate(['name' => 'Super Admin']);
        Role::firstOrCreate(['name' => 'Campus Manager']);
        Role::firstOrCreate(['name' => 'Guard']);
    }

    public function test_super_admin_can_access_admin_panel()
    {
        // Create test data
        $tenant = Tenant::factory()->create();
        $user = User::factory()->superAdmin()->create(['tenant_id' => $tenant->id]);
        
        // Assign Super Admin role
        $user->assignRole('Super Admin');
        
        // Debug: Check if user has role
        $this->assertTrue($user->hasRole('Super Admin'));
        
        // Test access
        $this->actingAs($user, 'web');         // ✅ use web guard
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

        $response = $this->get('/admin');      // no Accept: application/json
        
        // Debug: Check response
        if ($response->getStatusCode() !== 200) {
            echo "Response status: " . $response->getStatusCode() . PHP_EOL;
            echo "Response content: " . substr($response->getContent(), 0, 500) . PHP_EOL;
        }
        
        $response->assertOk()                       // or ->assertSee('Dashboard')
            ->assertSee('Dashboard');
    }

    public function test_non_super_admin_cannot_access_admin_panel()
    {
        // Create test data
        $tenant = Tenant::factory()->create();
        $user = User::factory()->guard()->create(['tenant_id' => $tenant->id]);
        
        // Assign Guard role
        $user->assignRole('Guard');
        
        // Test access
        $this->actingAs($user, 'web');
        $this->get('/admin')->assertForbidden(); // Filament returns 403 when authed but unauthorized
    }
}
