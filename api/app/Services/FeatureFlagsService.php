<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class FeatureFlagsService
{
    /**
     * Check if a feature is enabled for a tenant
     * 
     * @param string $key Feature key from config/features.php
     * @param int|string|null $tenantId Tenant ID (null for global check) - supports both int and UUID string
     * @return bool
     */
    public function enabled(string $key, int|string|null $tenantId = null): bool
    {
        // Get global default from config
        $global = config("features.$key");
        
        // If no tenant specified, return global setting
        if (!$tenantId) {
            return (bool) $global;
        }

        // Check tenant-specific override with Redis caching
        $cacheKey = "tenant:{$tenantId}:feature:$key";
        
        return Cache::remember($cacheKey, 300, function () use ($tenantId, $key, $global) {
            try {
                $row = DB::table('tenant_settings')
                    ->where('tenant_id', $tenantId)
                    ->where('key', 'features')
                    ->first();
                
                if ($row) {
                    $tenantFeatures = (array) json_decode($row->value, true);
                    $tenantValue = Arr::get($tenantFeatures, $key);
                    
                    // If tenant has explicit setting, use it
                    if ($tenantValue !== null) {
                        return (bool) $tenantValue;
                    }
                }
            } catch (\Exception $e) {
                // If database error, fallback to global
                \Log::warning("FeatureFlagsService: Failed to fetch tenant settings", [
                    'tenant_id' => $tenantId,
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Fallback to global setting
            return (bool) $global;
        });
    }

    /**
     * Set a feature flag for a tenant
     * 
     * @param string $key Feature key
     * @param bool $value Feature value
     * @param int|string $tenantId Tenant ID - supports both int and UUID string
     * @return void
     */
    public function set(string $key, bool $value, int|string $tenantId): void
    {
        try {
            // Get existing tenant settings
            $row = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('key', 'features')
                ->first();
            
            $features = $row ? (array) json_decode($row->value, true) : [];
            $features[$key] = $value;
            
            // Upsert tenant settings
            DB::table('tenant_settings')->updateOrInsert(
                [
                    'tenant_id' => $tenantId,
                    'key' => 'features'
                ],
                [
                    'value' => json_encode($features),
                    'updated_at' => now()
                ]
            );
            
            // Clear cache
            Cache::forget("tenant:{$tenantId}:feature:$key");
            
        } catch (\Exception $e) {
            \Log::error("FeatureFlagsService: Failed to set tenant feature flag", [
                'tenant_id' => $tenantId,
                'key' => $key,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get all feature flags for a tenant
     * 
     * @param int|string $tenantId Tenant ID - supports both int and UUID string
     * @return array
     */
    public function getAllForTenant(int|string $tenantId): array
    {
        $result = [];
        $allFeatures = config('features');
        
        foreach ($allFeatures as $key => $default) {
            $result[$key] = $this->enabled($key, $tenantId);
        }
        
        return $result;
    }

    /**
     * Clear cache for a tenant
     * 
     * @param int|string $tenantId Tenant ID - supports both int and UUID string
     * @param string|null $key Specific feature key (optional)
     * @return void
     */
    public function clearCache(int|string $tenantId, ?string $key = null): void
    {
        if ($key) {
            Cache::forget("tenant:{$tenantId}:feature:$key");
        } else {
            $allFeatures = array_keys(config('features'));
            foreach ($allFeatures as $featureKey) {
                Cache::forget("tenant:{$tenantId}:feature:$featureKey");
            }
        }
    }
}