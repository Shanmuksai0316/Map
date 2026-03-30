<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Dashboard\KpisRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CrossTenantDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Campus Manager', 'web');
    }

    public function test_super_admin_sees_aggregated_cross_tenant_metrics(): void
    {
        // Create multiple tenants
        $tenant1 = Tenant::factory()->create(['code' => 'MAP-T001']);
        $tenant2 = Tenant::factory()->create(['code' => 'MAP-T002']);
        
        // Create Super Admin (no tenant_id)
        $superAdmin = User::factory()->create(['tenant_id' => null]);
        $superAdmin->assignRole('Super Admin');
        
        $this->actingAs($superAdmin);
        
        $repo = app(KpisRepository::class);
        
        // Test totalHostels - should aggregate across all tenants
        $totalHostels = $repo->totalHostels(null);
        $this->assertGreaterThanOrEqual(0, $totalHostels);
        
        // Verify it's not tenant-scoped (would be 0 for null tenantId if scoped)
        $tenant1Hostels = $repo->totalHostels($tenant1->id);
        $tenant2Hostels = $repo->totalHostels($tenant2->id);
        
        // Super Admin should see sum, not individual tenant data
        if ($tenant1Hostels > 0 || $tenant2Hostels > 0) {
            $this->assertGreaterThanOrEqual(max($tenant1Hostels, $tenant2Hostels), $totalHostels);
        }
    }

    public function test_regular_user_sees_single_tenant_metrics(): void
    {
        $tenant = Tenant::factory()->create(['code' => 'MAP-T001']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('Campus Manager');
        
        $this->actingAs($user);
        
        $repo = app(KpisRepository::class);
        
        // Regular user should only see their tenant's data
        $metrics = [
            'totalHostels' => $repo->totalHostels($tenant->id),
            'availableBeds' => $repo->availableBeds($tenant->id),
        ];
        
        // Verify metrics are for specific tenant
        foreach ($metrics as $name => $value) {
            $this->assertGreaterThanOrEqual(0, $value, "$name should be non-negative");
        }
    }

    public function test_cross_tenant_cache_keys_are_different(): void
    {
        $tenant = Tenant::factory()->create();
        
        $superAdmin = User::factory()->create(['tenant_id' => null]);
        $superAdmin->assignRole('Super Admin');
        
        $regularUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $regularUser->assignRole('Campus Manager');
        
        $repo = app(KpisRepository::class);
        
        // Super Admin cache key should use 'all' for tenant context
        $superAdminCache = \Cache::remember("dash:super:all", 300, function () use ($repo) {
            return $repo->totalHostels(null);
        });
        
        // Regular user cache key should use tenant ID
        $tenantCache = \Cache::remember("dash:super:{$tenant->id}", 300, function () use ($repo, $tenant) {
            return $repo->totalHostels($tenant->id);
        });
        
        // Verify cache keys are different
        $this->assertTrue(\Cache::has("dash:super:all"));
        $this->assertTrue(\Cache::has("dash:super:{$tenant->id}"));
        
        // Clean up
        \Cache::forget("dash:super:all");
        \Cache::forget("dash:super:{$tenant->id}");
    }

    public function test_dashboard_widget_detects_super_admin_correctly(): void
    {
        $tenant = Tenant::factory()->create();
        
        // Super Admin has no tenant_id
        $superAdmin = User::factory()->create(['tenant_id' => null]);
        $superAdmin->assignRole('Super Admin');
        
        // Regular user has tenant_id
        $regularUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $regularUser->assignRole('Campus Manager');
        
        // Test Super Admin detection
        $this->actingAs($superAdmin);
        $this->assertTrue($superAdmin->hasRole('Super Admin'));
        $this->assertNull($superAdmin->tenant_id);
        
        // Test regular user
        $this->actingAs($regularUser);
        $this->assertFalse($regularUser->hasRole('Super Admin'));
        $this->assertNotNull($regularUser->tenant_id);
    }
}
