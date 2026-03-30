<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * TenantScope - Global Scope for Automatic Tenant Isolation
 * 
 * This scope automatically filters all queries by tenant_id.
 * It ensures that users can only access data from their own tenant.
 * 
 * Super Admins can bypass this scope using:
 * Model::withoutGlobalScope(TenantScope::class)->get();
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Get current tenant ID from session or request
        $tenantId = $this->getCurrentTenantId();
        
        if ($tenantId) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        }
    }

    /**
     * Get the current tenant ID from various sources
     */
    protected function getCurrentTenantId(): ?string
    {
        // Super Admin can access all tenants (no filtering)
        if (auth()->check() && auth()->user()->hasRole('Super Admin')) {
            return null; // Return null to skip filtering for Super Admin
        }

        // Try to get from authenticated user
        if (auth()->check() && auth()->user()->tenant_id) {
            return auth()->user()->tenant_id;
        }

        // Try to get from tenant context (if using tenancy package for routing)
        try {
            if (function_exists('tenant') && tenant()) {
                return tenant()->id;
            }
        } catch (\Exception $e) {
            // tenant() helper might not be available
        }

        // Try to get from session
        if (session()->has('tenant_id')) {
            return session('tenant_id');
        }

        // Try to get from request
        if (request()->has('tenant_id')) {
            return request('tenant_id');
        }

        return null;
    }
}

