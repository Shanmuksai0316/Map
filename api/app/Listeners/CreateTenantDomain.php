<?php

namespace App\Listeners;

use App\Events\TenantActivated;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Database\Models\Domain;

class CreateTenantDomain
{
    /**
     * Handle the event.
     *
     * Automatically creates a tenant domain when the tenant is activated.
     * This prevents "Tenant could not be identified on domain" errors for new tenants.
     */
    public function handle(TenantActivated $event): void
    {
        $tenant = $event->tenant;
        $wizardData = $event->wizardData ?? [];

        try {
            // 1. Try to read subdomain from wizard data (most up to date)
            $subdomain = $wizardData['tenant_info']['subdomain'] ?? null;

            // 2. Fall back to tenant->data if present
            $rawData = $tenant->data;
            if (!$subdomain && $rawData) {
                if (is_string($rawData)) {
                    $rawData = json_decode($rawData, true) ?: [];
                }
                if (is_array($rawData)) {
                    $subdomain = $rawData['tenant_info']['subdomain'] ?? $rawData['subdomain'] ?? null;
                }
            }

            // 3. Final fallback – derive from tenant code (e.g. MAP-PPCU -> ppcu)
            if (!$subdomain) {
                $subdomain = strtolower((string) str_replace('MAP-', '', $tenant->code));
            }

            // Normalize subdomain
            $subdomain = trim(strtolower($subdomain));

            if ($subdomain === '') {
                Log::warning('CreateTenantDomain: Empty subdomain, skipping domain creation', [
                    'tenant_id' => $tenant->id,
                    'tenant_code' => $tenant->code,
                ]);
                return;
            }

            // Build domain based on environment
            $domainName = app()->environment('local')
                ? "{$subdomain}.localhost"
                : "{$subdomain}.mapservices.in";

            // Check if the domain already exists
            $existing = Domain::where('domain', $domainName)->first();

            if ($existing) {
                if ($existing->tenant_id !== $tenant->id) {
                    Log::warning('CreateTenantDomain: Domain already exists for different tenant', [
                        'domain' => $domainName,
                        'existing_tenant_id' => $existing->tenant_id,
                        'current_tenant_id' => $tenant->id,
                    ]);
                } else {
                    Log::info('CreateTenantDomain: Domain already exists for tenant', [
                        'tenant_id' => $tenant->id,
                        'tenant_code' => $tenant->code,
                        'domain' => $domainName,
                    ]);
                }

                return;
            }

            // Create the domain for this tenant
            $tenant->domains()->create([
                'domain' => $domainName,
            ]);

            Log::info('CreateTenantDomain: Domain created for tenant', [
                'tenant_id' => $tenant->id,
                'tenant_code' => $tenant->code,
                'domain' => $domainName,
            ]);
        } catch (\Throwable $e) {
            // Do not block activation – just log and allow activation to proceed
            Log::error('CreateTenantDomain: Failed to create domain for tenant', [
                'tenant_id' => $tenant->id ?? null,
                'tenant_code' => $tenant->code ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}


