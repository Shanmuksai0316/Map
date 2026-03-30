<?php

namespace App\Support;

use App\Services\FeatureFlagsService;

class Feature
{
    /**
     * Check if a feature is enabled globally or for a tenant
     * 
     * @deprecated Use FeatureFlagsService directly for better control
     */
    public static function isEnabled(string $key, ?int $tenantId = null): bool
    {
        // Delegate to FeatureFlagsService for consistency
        // This ensures tenant overrides work correctly (stored as JSON under 'features' key)
        return app(FeatureFlagsService::class)->enabled($key, $tenantId);
    }

    /**
     * Check if a feature is enabled for a tenant
     */
    public static function isEnabledForTenant(string $key, ?int $tenantId = null): bool
    {
        // Delegate to isEnabled for consistency
        return self::isEnabled($key, $tenantId);
    }

    /**
     * Set a feature flag for a tenant
     */
    public static function setForTenant(string $key, bool $value, int $tenantId): void
    {
        app(FeatureFlagsService::class)->set($key, $value, $tenantId);
    }

    /**
     * Get all feature flags for a tenant
     */
    public static function getAllForTenant(int $tenantId): array
    {
        return app(FeatureFlagsService::class)->getAllForTenant($tenantId);
    }

    /**
     * Clear feature flag cache for a tenant
     */
    public static function clearCache(int $tenantId, ?string $key = null): void
    {
        app(FeatureFlagsService::class)->clearCache($tenantId, $key);
    }
}