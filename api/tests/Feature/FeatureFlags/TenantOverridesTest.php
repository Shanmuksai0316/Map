<?php

namespace Tests\Feature\FeatureFlags;

use App\Models\Tenant;
use App\Support\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantOverridesTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_flag_uses_global_default_when_no_tenant_override()
    {
        // Set global default
        config(['features.test_feature' => true]);
        
        $result = Feature::isEnabled('test_feature', 1);
        
        $this->assertTrue($result);
    }

    public function test_feature_flag_uses_tenant_override_when_set()
    {
        // Set global default to false
        config(['features.test_feature' => false]);
        
        // Set tenant override to true
        DB::table('tenant_settings')->insert([
            'tenant_id' => 1,
            'key' => 'feature:test_feature',
            'value' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $result = Feature::isEnabled('test_feature', 1);
        
        $this->assertTrue($result);
    }

    public function test_feature_flag_caches_tenant_override()
    {
        config(['features.test_feature' => false]);
        
        DB::table('tenant_settings')->insert([
            'tenant_id' => 1,
            'key' => 'feature:test_feature',
            'value' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // First call should cache the result
        Feature::isEnabled('test_feature', 1);
        
        // Remove from database
        DB::table('tenant_settings')->where('tenant_id', 1)->delete();
        
        // Should still return cached result
        $result = Feature::isEnabled('test_feature', 1);
        $this->assertTrue($result);
        
        // Clear cache and try again
        Cache::forget('ff:1:test_feature');
        $result = Feature::isEnabled('test_feature', 1);
        $this->assertFalse($result);
    }

    public function test_feature_flag_works_without_tenant_id()
    {
        config(['features.test_feature' => true]);
        
        $result = Feature::isEnabled('test_feature');
        
        $this->assertTrue($result);
    }
}



