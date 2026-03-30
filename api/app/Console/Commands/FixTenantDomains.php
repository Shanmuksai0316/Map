<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FixTenantDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:fix-domains {--dry-run : Show what would be fixed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix tenants that are missing domain records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('Checking for tenants without domains...');

        // Find tenants without domains
        $tenantsWithoutDomains = Tenant::doesntHave('domains')->get();

        if ($tenantsWithoutDomains->isEmpty()) {
            $this->info('✅ All tenants have domains. Nothing to fix.');
            return Command::SUCCESS;
        }

        $this->warn("Found {$tenantsWithoutDomains->count()} tenant(s) without domains:");
        
        $fixed = 0;
        $failed = 0;

        foreach ($tenantsWithoutDomains as $tenant) {
            $this->line('');
            $this->line("Tenant: {$tenant->name} ({$tenant->code})");
            
            // Generate subdomain from code (lowercase, sanitized)
            $subdomain = Str::slug(strtolower($tenant->code));
            
            // Build full domain based on environment
            $domainSuffix = config('app.domain', 'mapmars.com');
            $domain = env('APP_ENV') === 'local' 
                ? $subdomain . '.localhost'
                : $subdomain . '.' . $domainSuffix;
            
            $this->line("  → Would create domain: {$domain}");

            if (!$dryRun) {
                try {
                    $tenant->domains()->create([
                        'domain' => $domain,
                    ]);
                    $this->info('  ✅ Domain created successfully');
                    $fixed++;
                } catch (\Exception $e) {
                    $this->error('  ❌ Failed to create domain: ' . $e->getMessage());
                    $failed++;
                }
            }
        }

        $this->line('');
        
        if ($dryRun) {
            $this->info("Would fix {$tenantsWithoutDomains->count()} tenant(s).");
            $this->info('Run without --dry-run to apply changes.');
        } else {
            $this->info("✅ Fixed: {$fixed}");
            if ($failed > 0) {
                $this->error("❌ Failed: {$failed}");
            }
        }

        return Command::SUCCESS;
    }
}
