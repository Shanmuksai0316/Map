<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantFeatureFlag extends Model
{
    protected $fillable = [
        'tenant_id',
        'feature_key',
        'is_enabled',
        'config',
        'enabled_at',
        'disabled_at',
        'enabled_by_user_id',
        'notes',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'config' => 'array',
        'enabled_at' => 'datetime',
        'disabled_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the feature flag.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who enabled the feature.
     */
    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by_user_id');
    }

    /**
     * Enable the feature flag.
     */
    public function enable(int $userId = null, string $notes = null): void
    {
        $this->update([
            'is_enabled' => true,
            'enabled_at' => now(),
            'disabled_at' => null,
            'enabled_by_user_id' => $userId,
            'notes' => $notes,
        ]);
    }

    /**
     * Disable the feature flag.
     */
    public function disable(string $notes = null): void
    {
        $this->update([
            'is_enabled' => false,
            'disabled_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Get all feature flags for a tenant.
     */
    public static function getTenantFlags(string $tenantId): array
    {
        $flags = self::where('tenant_id', $tenantId)->get();
        
        $result = [];
        foreach ($flags as $flag) {
            $result[$flag->feature_key] = [
                'enabled' => $flag->is_enabled,
                'config' => $flag->config,
                'enabled_at' => $flag->enabled_at,
                'notes' => $flag->notes,
            ];
        }
        
        return $result;
    }

    /**
     * Set feature flag for a tenant.
     */
    public static function setTenantFlag(
        string $tenantId,
        string $featureKey,
        bool $enabled,
        array $config = null,
        int $userId = null,
        string $notes = null
    ): self {
        $flag = self::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'feature_key' => $featureKey,
            ],
            [
                'is_enabled' => $enabled,
                'config' => $config,
                'enabled_by_user_id' => $userId,
                'notes' => $notes,
            ]
        );

        if ($enabled) {
            $flag->update(['enabled_at' => now(), 'disabled_at' => null]);
        } else {
            $flag->update(['disabled_at' => now()]);
        }

        return $flag;
    }

    /**
     * Get default feature flags for a new tenant.
     */
    public static function getDefaultFlags(): array
    {
        return [
            'security_module' => false,
            'sports_module' => false,
            'laundry_module' => false,
            'gate_pass_qr' => true,
            'offline_sync' => true,
        ];
    }

    /**
     * Initialize default flags for a new tenant.
     */
    public static function initializeTenantFlags(string $tenantId, int $userId = null): void
    {
        $defaultFlags = self::getDefaultFlags();
        
        foreach ($defaultFlags as $featureKey => $enabled) {
            self::setTenantFlag(
                $tenantId,
                $featureKey,
                $enabled,
                null,
                $userId,
                'Default flag for new tenant'
            );
        }
    }
}
