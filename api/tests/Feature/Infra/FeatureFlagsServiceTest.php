<?php

namespace Tests\Feature\Infra;

use App\Models\Tenant;
use App\Services\FeatureFlagsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FeatureFlagsServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeatureFlagsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FeatureFlagsService::class);
        Cache::flush();
    }

    public function test_returns_global_config_when_no_tenant_provided(): void
    {
        // Test attendance module (default true)
        $this->assertTrue($this->service->enabled('attendance_module'));
        
        // Test laundry module (default false)
        $this->assertFalse($this->service->enabled('laundry_module'));
    }

    public function test_returns_global_config_when_tenant_has_no_settings(): void
    {
        $tenant = Tenant::factory()->create();
        
        // Should return global config since no tenant settings exist
        $this->assertTrue($this->service->enabled('attendance_module', $tenant->id));
        $this->assertFalse($this->service->enabled('laundry_module', $tenant->id));
    }

    public function test_tenant_override_takes_precedence_over_global(): void
    {
        $tenant = Tenant::factory()->create();
        
        // Set tenant-specific feature flags
        $this->service->set('laundry_module', true, $tenant->id);
        $this->service->set('attendance_module', false, $tenant->id);
        
        // Should return tenant-specific values
        $this->assertTrue($this->service->enabled('laundry_module', $tenant->id));
        $this->assertFalse($this->service->enabled('attendance_module', $tenant->id));
        
        // Other tenants should still get global config
        $otherTenant = Tenant::factory()->create();
        $this->assertFalse($this->service->enabled('laundry_module', $otherTenant->id));
        $this->assertTrue($this->service->enabled('attendance_module', $otherTenant->id));
    }

    public function test_redis_caching_works_correctly(): void
    {
        $tenant = Tenant::factory()->create();
        
        // Set a feature flag
        $this->service->set('sports_module', true, $tenant->id);
        
        // First call should hit database and cache
        $this->assertTrue($this->service->enabled('sports_module', $tenant->id));
        
        // Verify it's cached
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:feature:sports_module"));
        
        // Second call should use cache (we can't easily test this without mocking)
        $this->assertTrue($this->service->enabled('sports_module', $tenant->id));
    }

    public function test_fallback_to_global_on_database_error(): void
    {
        $tenant = Tenant::factory()->create();
        
        // Set up a feature flag
        $this->service->set('payments_module', true, $tenant->id);
        $this->assertTrue($this->service->enabled('payments_module', $tenant->id));
        
        // Clear cache to force database lookup
        $this->service->clearCache($tenant->id, 'payments_module');
        
        // Simulate database error by dropping the table
        Schema::drop('tenant_settings');
        
        // Should fallback to global config
        $this->assertFalse($this->service->enabled('payments_module', $tenant->id));
    }

    public function test_set_feature_flag_persists_to_database(): void
    {
        $tenant = Tenant::factory()->create();
        
        // Set a feature flag
        $this->service->set('otp_stepup', true, $tenant->id);
        
        // Verify it's in database
        $row = DB::table('tenant_settings')
            ->where('tenant_id', $tenant->id)
            ->where('key', 'features')
            ->first();
        
        $this->assertNotNull($row);
        
        $features = json_decode($row->value, true);
        $this->assertTrue($features['otp_stepup']);
    }

    public function test_get_all_features_for_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        
        // Set some tenant-specific overrides
        $this->service->set('laundry_module', true, $tenant->id);
        $this->service->set('sports_module', true, $tenant->id);
        
        $allFeatures = $this->service->getAllForTenant($tenant->id);
        
        // Should include all features from config
        $this->assertArrayHasKey('attendance_module', $allFeatures);
        $this->assertArrayHasKey('laundry_module', $allFeatures);
        $this->assertArrayHasKey('sports_module', $allFeatures);
        $this->assertArrayHasKey('payments_module', $allFeatures);
        
        // Should reflect tenant overrides
        $this->assertTrue($allFeatures['laundry_module']);
        $this->assertTrue($allFeatures['sports_module']);
        
        // Should reflect global defaults for unset features
        $this->assertTrue($allFeatures['attendance_module']); // global default true
        $this->assertFalse($allFeatures['payments_module']); // global default false
    }

    public function test_clear_cache_removes_cached_values(): void
    {
        $tenant = Tenant::factory()->create();
        
        // Set and access a feature flag to cache it
        $this->service->set('laundry_module', true, $tenant->id);
        $this->service->enabled('laundry_module', $tenant->id);
        
        // Verify it's cached
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:feature:laundry_module"));
        
        // Clear cache for specific key
        $this->service->clearCache($tenant->id, 'laundry_module');
        
        // Cache should be gone
        $this->assertFalse(Cache::has("tenant:{$tenant->id}:feature:laundry_module"));
    }

    public function test_clear_cache_removes_all_features_for_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        
        // Set multiple feature flags and access them to cache
        $this->service->set('laundry_module', true, $tenant->id);
        $this->service->set('sports_module', true, $tenant->id);
        $this->service->enabled('laundry_module', $tenant->id);
        $this->service->enabled('sports_module', $tenant->id);
        
        // Verify they're cached
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:feature:laundry_module"));
        $this->assertTrue(Cache::has("tenant:{$tenant->id}:feature:sports_module"));
        
        // Clear all cache for tenant
        $this->service->clearCache($tenant->id);
        
        // All caches should be gone
        $this->assertFalse(Cache::has("tenant:{$tenant->id}:feature:laundry_module"));
        $this->assertFalse(Cache::has("tenant:{$tenant->id}:feature:sports_module"));
    }

    public function test_update_existing_tenant_settings(): void
    {
        $tenant = Tenant::factory()->create();
        
        // Set initial feature flags
        $this->service->set('laundry_module', true, $tenant->id);
        $this->service->set('sports_module', false, $tenant->id);
        
        // Update one feature flag
        $this->service->set('sports_module', true, $tenant->id);
        
        // Verify both are still there with correct values
        $row = DB::table('tenant_settings')
            ->where('tenant_id', $tenant->id)
            ->where('key', 'features')
            ->first();
        
        $features = json_decode($row->value, true);
        $this->assertTrue($features['laundry_module']);
        $this->assertTrue($features['sports_module']);
    }
}
