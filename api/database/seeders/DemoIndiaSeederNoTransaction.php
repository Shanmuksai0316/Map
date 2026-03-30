<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class DemoIndiaSeederNoTransaction extends Seeder
{
    /**
     * Playwright-compatible version that runs seeding without transactions
     * This allows tenant database creation to work properly
     */
    public function run(): void
    {
        $this->info('Starting Playwright seeding without transactions...');

        // Create tenants first (central database)
        $tenants = collect([
            ['name' => 'Saraswati Institute of Technology', 'code' => 'SIT'],
            ['name' => 'Nalanda University (West Campus)', 'code' => 'NUW'],
            ['name' => 'Vidya Bharati College', 'code' => 'VBC'],
        ])->map(function ($t) {
            return Tenant::firstOrCreate(['code' => $t['code']], ['name' => $t['name']]);
        });

        $this->info('Created ' . $tenants->count() . ' tenants');

        // For each tenant, run migrations and seeding in separate processes
        foreach ($tenants as $tenant) {
            $this->info("Seeding tenant: {$tenant->name}");
            $this->seedTenant($tenant);
        }

        $this->info('Playwright seeding completed successfully!');
    }

    /**
     * Seed a tenant by running migrations and seeding in separate process
     */
    private function seedTenant(Tenant $tenant): void
    {
        // Run tenant migrations
        Artisan::call('tenants:migrate', ['--tenants' => $tenant->id]);
        
        // Run tenant seeding
        Artisan::call('tenants:seed', [
            '--tenants' => $tenant->id,
            '--class' => 'Database\\Seeders\\TenantSeeder'
        ]);
    }
}


