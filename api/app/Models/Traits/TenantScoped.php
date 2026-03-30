<?php

namespace App\Models\Traits;

use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * TenantScoped Trait
 * 
 * Automatically scope all queries to the authenticated user's tenant.
 * Prevents accidental cross-tenant data leaks.
 * 
 * Usage:
 *   class Student extends Model {
 *       use TenantScoped;
 *   }
 * 
 * To bypass tenant scoping in specific queries:
 *   Student::withoutGlobalScope(TenantScope::class)->get();
 */
trait TenantScoped
{
    /**
     * Boot the tenant scoped trait for a model.
     */
    protected static function bootTenantScoped(): void
    {
        // Apply TenantScope global scope
        static::addGlobalScope(new TenantScope());

        // Automatically set tenant_id on model creation
        static::creating(function (Model $model) {
            if (!$model->tenant_id) {
                $tenantId = static::getCurrentTenantId();
                if ($tenantId) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }

    /**
     * Get current tenant ID from various sources
     */
    protected static function getCurrentTenantId(): ?string
    {
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

        if (app()->bound('testing.default_tenant_id')) {
            return app('testing.default_tenant_id');
        }

        return null;
    }
}

