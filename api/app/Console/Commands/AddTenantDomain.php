<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Stancl\Tenancy\Database\Models\Domain;

class AddTenantDomain extends Command
{
    protected $signature = 'tenant:add-domain {code} {domain?}';
    protected $description = 'Add a domain to a tenant. If domain is not provided, it will be generated from tenant data.';

    public function handle(): int
    {
        $code = $this->argument('code');
        $domainArg = $this->argument('domain');

        $tenant = Tenant::where('code', $code)->first();
        
        if (!$tenant) {
            $this->error("Tenant with code '{$code}' not found.");
            return self::FAILURE;
        }

        $this->info("Found tenant: {$tenant->name} ({$tenant->code})");

        // Get subdomain from tenant data or generate from code
        $data = is_string($tenant->data) ? json_decode($tenant->data, true) : $tenant->data;
        $subdomain = $data['tenant_info']['subdomain'] ?? strtolower(str_replace('MAP-', '', $tenant->code));

        // Determine domain
        if ($domainArg) {
            $domainName = $domainArg;
        } else {
            $domainName = "{$subdomain}.mapservices.in";
        }

        // Check if domain already exists
        $existing = Domain::where('domain', $domainName)->first();
        
        if ($existing) {
            if ($existing->tenant_id === $tenant->id) {
                $this->info("Domain '{$domainName}' already exists and is correctly linked to this tenant.");
                return self::SUCCESS;
            } else {
                $this->error("Domain '{$domainName}' already exists but belongs to a different tenant (ID: {$existing->tenant_id}).");
                return self::FAILURE;
            }
        }

        // Create domain
        try {
            $domain = $tenant->domains()->create([
                'domain' => $domainName,
            ]);
            
            $this->info("✅ Domain '{$domainName}' created successfully!");
            $this->info("Access URL: https://{$domainName}/campus-manager");
            
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create domain: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}

