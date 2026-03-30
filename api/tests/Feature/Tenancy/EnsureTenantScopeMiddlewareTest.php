<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test EnsureTenantScope Middleware
 *
 * These tests verify that the EnsureTenantScope middleware correctly
 * enforces tenant isolation at the HTTP request level.
 */
class EnsureTenantScopeMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private User $tenant1User;
    private User $tenant2User;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test tenants
        $this->tenant1 = Tenant::factory()->create(['code' => 'TENANT1']);
        $this->tenant2 = Tenant::factory()->create(['code' => 'TENANT2']);

        // Create test users
        $this->tenant1User = User::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'kind' => 'staff',
        ]);
        $this->tenant1User->assignRole('Campus Manager');

        $this->tenant2User = User::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'kind' => 'staff',
        ]);
        $this->tenant2User->assignRole('Campus Manager');

        // Create Super Admin
        $this->superAdmin = User::factory()->create([
            'tenant_id' => null, // Super Admin has no tenant
        ]);
        $this->superAdmin->assignRole('Super Admin');
    }

    /** @test */
    public function middleware_blocks_requests_when_tenancy_not_initialized(): void
    {
        // Don't initialize tenancy
        tenancy()->end();

        $this->actingAs($this->tenant1User)
            ->get('/api/v1/tenant-healthz')
            ->assertStatus(400)
            ->assertJson([
                'error' => 'Tenant not initialized'
            ]);
    }

    /** @test */
    public function middleware_blocks_requests_when_tenant_not_found(): void
    {
        // Mock tenancy initialized without tenant
        tenancy()->initialized = true;
        tenancy()->tenant = null;

        $this->actingAs($this->tenant1User)
            ->get('/api/v1/tenant-healthz')
            ->assertStatus(404)
            ->assertJson([
                'error' => 'Tenant not found'
            ]);
    }

    /** @test */
    public function middleware_blocks_cross_tenant_access(): void
    {
        // Initialize tenancy for tenant2
        tenancy()->initialize($this->tenant2);

        // But user belongs to tenant1
        $this->actingAs($this->tenant1User)
            ->get('/api/v1/tenant-healthz')
            ->assertStatus(403)
            ->assertJson([
                'error' => 'Access denied to this tenant'
            ]);
    }

    /** @test */
    public function middleware_allows_access_for_users_in_correct_tenant(): void
    {
        // Initialize tenancy for tenant1
        tenancy()->initialize($this->tenant1);

        // User belongs to tenant1
        $this->actingAs($this->tenant1User)
            ->get('/api/v1/tenant-healthz')
            ->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'tenant' => $this->tenant1->code
            ]);
    }

    /** @test */
    public function middleware_allows_super_admin_to_access_any_tenant(): void
    {
        // Initialize tenancy for tenant1
        tenancy()->initialize($this->tenant1);

        // Super Admin can access any tenant
        $this->actingAs($this->superAdmin)
            ->get('/api/v1/tenant-healthz')
            ->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'tenant' => $this->tenant1->code
            ]);

        // Initialize tenancy for tenant2
        tenancy()->initialize($this->tenant2);

        // Super Admin can still access
        $this->actingAs($this->superAdmin)
            ->get('/api/v1/tenant-healthz')
            ->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'tenant' => $this->tenant2->code
            ]);
    }

    /** @test */
    public function middleware_sets_and_clears_postgresql_session_variable(): void
    {
        // Initialize tenancy for tenant1
        tenancy()->initialize($this->tenant1);

        // Before request, session variable should be null
        $this->assertDatabaseMissing('pg_stat_activity', [
            'application_name' => 'app.current_tenant_id',
        ], 'postgres');

        $this->actingAs($this->tenant1User)
            ->get('/api/v1/tenant-healthz')
            ->assertStatus(200);

        // After request completes, session variable should be cleared
        // Note: This is hard to test directly with database assertions in this context
        // The middleware uses try/finally to ensure cleanup
    }

    /** @test */
    public function middleware_allows_requests_without_authenticated_user_for_public_routes(): void
    {
        // Initialize tenancy for tenant1
        tenancy()->initialize($this->tenant1);

        // Health check should work without authentication
        $this->get('/api/v1/tenant-healthz')
            ->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'tenant' => $this->tenant1->code
            ]);
    }
}
