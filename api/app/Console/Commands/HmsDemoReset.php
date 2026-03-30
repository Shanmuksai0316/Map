<?php

namespace App\Console\Commands;

use Database\Seeders\DemoTenantSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class HmsDemoReset extends Command
{
    protected $signature = 'hms:demo:reset {--fresh : Drop and recreate database} {--force : Skip confirmation prompts}';

    protected $description = 'Reset demo data for MAP-HMS';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->warn('⚠️  Dropping and recreating database...');

            if (!$this->option('force') && !$this->confirm('This will DELETE ALL DATA. Continue?')) {
                $this->info('Aborted.');

                return 1;
            }

            Artisan::call('migrate:fresh', ['--force' => true]);
            $this->info('✅ Database recreated');
        }

        $this->info('🌱 Seeding roles and permissions...');
        Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        $this->info('🌱 Seeding demo tenant data...');
        Artisan::call('db:seed', ['--class' => DemoTenantSeeder::class, '--force' => true]);

        $this->info('✅ Demo data reset complete!');
        $this->newLine();
        $this->info('📧 Login credentials: docs/demo/DemoCredentials_v1.2.md');
        $this->info('📖 Demo guide: docs/demo/DemoGuide_v1.2.md');
        
        $this->newLine();
        $this->info('🔑 Quick Access (Phone/OTP Login):');
        $this->info('Development OTP: 123456 (works for all users)');
        $this->newLine();
        $this->table(
            ['Role', 'Phone', 'Panel URL'],
            [
                ['Super Admin', '+919900000001', 'http://localhost:8000/admin'],
                ['Campus Manager', '+919900000002', 'http://demo-college.localhost:8000/campus-manager'],
                ['Rector', '+919900000003', 'http://demo-college.localhost:8000/campus-manager'],
            ]
        );

        return 0;
    }
}