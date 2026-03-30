<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set PostgreSQL Session Tenant ID
 * 
 * This middleware sets the PostgreSQL session variable 'app.current_tenant_id'
 * when tenancy is initialized. This allows Row Level Security (RLS) policies
 * to automatically filter queries by tenant_id at the database level.
 * 
 * This middleware should run after InitializeTenancyByDomain.
 * 
 * Also provides a static method for setting the session variable from
 * background jobs and other non-HTTP contexts.
 */
class SetPostgresSessionTenant
{
    /**
     * Handle an incoming request.
     * 
     * Uses try/finally to ensure session variable is always cleared,
     * even if an exception occurs during request processing.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->applyTenantSessionVariable();

        try {
            return $next($request);
        } finally {
            $this->resetTenantSessionVariable();
        }
    }

    /**
     * Set PostgreSQL session variables for RLS policies.
     * Can be called from HTTP requests or background jobs.
     * 
     * @param string|null $tenantId Optional tenant ID. If null, uses tenant() helper.
     * @param int|null $userId Optional user ID. If null, uses auth()->id().
     */
    public static function setTenantSessionVariable(?string $tenantId = null, ?int $userId = null): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! $tenantId) {
            if (function_exists('tenant') && tenant()) {
                $tenantId = tenant()->id;
            } elseif (auth()->check() && auth()->user()?->tenant_id) {
                $tenantId = auth()->user()->tenant_id;
            }
        }

        if (! $userId && auth()->check()) {
            $userId = auth()->id();
        }

        if ($tenantId) {
            // Set PostgreSQL session variable for RLS policies (tenant isolation)
            // Use set_config() which supports parameter binding, unlike SET command
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);
        }

        if ($userId) {
            // Set PostgreSQL session variable for RLS policies (user-level access control)
            DB::statement("SELECT set_config('app.current_user_id', ?, false)", [(string) $userId]);
        }
    }

    /**
     * Clear PostgreSQL session variables after request/job completion.
     */
    public static function clearTenantSessionVariable(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        try {
            // Use RESET command for custom GUCs (SET ... = NULL is invalid syntax)
            DB::statement("RESET app.current_tenant_id");
            DB::statement("RESET app.current_user_id");
        } catch (\Throwable $error) {
            // Ignore transaction-aborted errors caused by earlier failures so tests/apps continue.
            // Logging is intentionally omitted to avoid exposing sensitive data.
        }
    }

    /**
     * Set session variable from current tenant context.
     */
    protected function applyTenantSessionVariable(): void
    {
        static::setTenantSessionVariable();
    }

    /**
     * Clear session variable after request.
     */
    protected function resetTenantSessionVariable(): void
    {
        static::clearTenantSessionVariable();
    }
}

