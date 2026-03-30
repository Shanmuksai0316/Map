<?php

namespace App\Models;

use App\Enums\TenantStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant Model (Single Shared Database Architecture)
 * 
 * This model represents a tenant in the single shared database architecture.
 * All tenant data is stored in a single shared PostgreSQL database with
 * tenant_id scoping for isolation.
 * 
 * Tenant Hierarchy:
 * - Tenant (University/Organization) → All data in shared database
 *   ├── Domains (Subdomains for access)
 *   └── All tenant data lives in central database with tenant_id
 * 
 * Features:
 * - Subdomain routing (e.g., jnu.yourapp.com)
 * - Tenant isolation via tenant_id + TenantScope + RLS policies
 * - Custom tenant settings (addons, configuration)
 * - Lifecycle management (active, suspended, archived)
 * - Subscription tracking (offline sales model)
 * 
 * Note: TenantWithDatabase interface and HasDatabase trait removed
 * as we now use a single shared database with tenant_id scoping.
 */
class Tenant extends BaseTenant
{
    use HasDomains, HasFactory, SoftDeletes;

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'status' => TenantStatus::class,
        'suspended_at' => 'datetime',
        'archived_at' => 'datetime',
        'subscription_starts_at' => 'date',
        'subscription_ends_at' => 'date',
        'addon_security' => 'boolean',
        'addon_sports' => 'boolean',
        'addon_laundry' => 'boolean',
        'settings' => 'array',
        'data' => 'array', // Required by stancl/tenancy
    ];

    /**
     * Custom columns beyond the default stancl tenants table.
     * These will be stored in the central database tenants table.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'code',
            'name',
            'status',
            'suspended_at',
            'suspended_reason',
            'archived_at',
            'subscription_plan',
            'subscription_amount',
            'subscription_starts_at',
            'subscription_ends_at',
            'payment_mode',
            'payment_notes',
            'addon_security',
            'addon_sports',
            'addon_laundry',
            'settings',
            'deleted_at',
        ];
    }

    public function getDataAttribute($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $raw = $this->getRawOriginal('data');

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            return json_decode($raw, true) ?? [];
        }

        return [];
    }
    
    /**
     * Get status as string for display
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->status instanceof TenantStatus) {
            return $this->status->label();
        }
        
        return ucfirst($this->status ?? 'unknown');
    }

    /**
     * Get the tenant's primary subdomain.
     */
    public function getPrimaryDomainAttribute(): ?string
    {
        return $this->domains()->first()?->domain;
    }

    /**
     * Check if a specific addon is enabled.
     */
    public function hasAddon(string $addon): bool
    {
        $field = "addon_{$addon}";
        return $this->{$field} ?? false;
    }

    /**
     * Get a setting value.
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set a setting value.
     */
    public function setSetting(string $key, $value): self
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        return $this;
    }

    /**
     * Get all campuses for this tenant.
     * All campuses are in the single shared database with tenant_id scoping.
     */
    public function campuses(): HasMany
    {
        return $this->hasMany(Campus::class, 'tenant_id');
    }

    /**
     * Get all hostels for this tenant.
     * All hostels are in the single shared database with tenant_id scoping.
     */
    public function hostels(): HasMany
    {
        return $this->hasMany(Hostel::class, 'tenant_id');
    }

    /**
     * Get all users for this tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    /**
     * Archive the tenant (long-term inactive, read-only, non-reactivable)
     */
    public function archive(): void
    {
        $this->update([
            'status' => TenantStatus::ARCHIVED,
            'archived_at' => now(),
        ]);
    }

    /**
     * Suspend the tenant temporarily (e.g., non-payment)
     */
    public function suspend(?string $reason = null): void
    {
        $this->update([
            'status' => TenantStatus::SUSPENDED,
            'suspended_at' => now(),
            'suspended_reason' => $reason,
        ]);
    }

    /**
     * Reactivate a suspended or archived tenant
     */
    public function reactivate(): void
    {
        $this->update([
            'status' => TenantStatus::ACTIVE,
            'suspended_at' => null,
            'suspended_reason' => null,
            'archived_at' => null,
        ]);
    }

    /**
     * Check if tenant can access the system
     */
    public function canAccess(): bool
    {
        if ($this->status instanceof TenantStatus) {
            return $this->status->canAccess();
        }
        
        // Fallback for string comparison
        return in_array($this->status, ['active', 'provisioning']);
    }

    /**
     * Check if subscription is expired
     */
    public function isSubscriptionExpired(): bool
    {
        if (!$this->subscription_ends_at) {
            return false;
        }
        
        return $this->subscription_ends_at->isPast();
    }

    /**
     * Scope: Active tenants only
     */
    public function scopeActive($query)
    {
        return $query->where('status', TenantStatus::ACTIVE->value);
    }

    /**
     * Scope: Suspended tenants
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', TenantStatus::SUSPENDED->value);
    }


    /**
     * Scope: Archived tenants
     */
    public function scopeArchived($query)
    {
        return $query->where('status', TenantStatus::ARCHIVED->value);
    }

    /**
     * Scope: Provisioning (onboarding in progress)
     */
    public function scopeProvisioning($query)
    {
        return $query->where('status', TenantStatus::PROVISIONING->value);
    }
}

