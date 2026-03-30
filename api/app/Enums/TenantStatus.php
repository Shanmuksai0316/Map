<?php

namespace App\Enums;

/**
 * Tenant Lifecycle Status
 * 
 * Defines the possible states a tenant can be in throughout its lifecycle.
 * Updated: Removed SUSPENDED and DELETED; ARCHIVED is non-reactivable.
 */
enum TenantStatus: string
{
    case PROVISIONING = 'provisioning';  // During onboarding, not yet active
    case ACTIVE = 'active';              // Fully operational
    case SUSPENDED = 'suspended';        // Temporarily blocked (e.g., non-payment)
    case ARCHIVED = 'archived';          // Long-term inactive, read-only, non-reactivable

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match($this) {
            self::PROVISIONING => 'Provisioning',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::ARCHIVED => 'Archived',
        };
    }

    /**
     * Get color for badge display
     */
    public function color(): string
    {
        return match($this) {
            self::PROVISIONING => 'warning',
            self::ACTIVE => 'success',
            self::SUSPENDED => 'danger',
            self::ARCHIVED => 'secondary',
        };
    }

    /**
     * Check if tenant can access the system
     */
    public function canAccess(): bool
    {
        return in_array($this, [self::ACTIVE, self::PROVISIONING]);
    }

    /**
     * Check if tenant can be reactivated
     */
    public function canReactivate(): bool
    {
        return in_array($this, [self::SUSPENDED, self::ARCHIVED]);
    }
}

