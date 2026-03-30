<?php

namespace Tests\Feature\Middleware;

use App\Enums\TenantStatus;
use App\Http\Middleware\CheckTenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CheckTenantStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Campus Manager', 'guard_name' => 'web']);
    }

    public function test_active_tenant_can_access_system(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('Campus Manager');
        
        $this->actingAs($user);
        
        $response = $this->get('/api/v1/healthz');
        
        // Should not be blocked
        $response->assertStatus(200);
    }

    public function test_provisioning_tenant_can_access_system(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'provisioning']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('Campus Manager');
        
        $this->actingAs($user);
        
        $response = $this->get('/api/v1/healthz');
        
        // Should not be blocked
        $response->assertStatus(200);
    }

    public function test_suspended_tenant_is_blocked_from_access(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspended_reason' => 'Non-payment',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('Campus Manager');
        
        $middleware = new CheckTenantStatus();
        
        $request = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(fn () => $user);
        
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));
        
        // Should be blocked
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('suspended', $response->getContent());
    }

    public function test_archived_tenant_is_blocked_from_access(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => 'archived',
            'archived_at' => now(),
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('Campus Manager');
        
        $middleware = new CheckTenantStatus();
        
        $request = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(fn () => $user);
        
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));
        
        // Should be blocked
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_super_admin_without_tenant_id_is_not_blocked(): void
    {
        $superAdmin = User::factory()->create(['tenant_id' => null]);
        $superAdmin->assignRole('Super Admin');
        
        $middleware = new CheckTenantStatus();
        
        $request = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(fn () => $superAdmin);
        
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));
        
        // Super Admin should not be blocked
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_unauthenticated_request_is_not_blocked(): void
    {
        $middleware = new CheckTenantStatus();
        
        $request = Request::create('/api/v1/test', 'GET');
        
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));
        
        // Should pass through
        $this->assertEquals(200, $response->getStatusCode());
    }
}
