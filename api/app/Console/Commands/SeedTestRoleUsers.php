<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SeedTestRoleUsers extends Command
{
    protected $signature = 'test:seed-role-users {--tenant=MAP-DEMO-COLLEGE : Tenant code to create users in}';

    protected $description = 'Create test users for all 8 staff roles for E2E testing';

    public function handle(): int
    {
        $tenantCode = $this->option('tenant');
        
        $tenant = Tenant::where('code', $tenantCode)->first();
        
        if (!$tenant) {
            $this->error("Tenant not found: {$tenantCode}");
            $this->info("Available tenants:");
            Tenant::all()->each(fn($t) => $this->line("  - {$t->code}: {$t->name}"));
            return 1;
        }

        $this->info("Creating test users for tenant: {$tenant->name} ({$tenant->code})");

        // Test users for all 8 staff roles
        // Using simple phone numbers without +91 prefix for easier testing
        $testUsers = [
            [
                'name' => 'Test Campus Manager',
                'email' => 'test.cm@demo.map.ac.in',
                'phone' => '8888888881', // Campus Manager
                'kind' => 'CampusManager',
                'role' => 'Campus Manager',
            ],
            [
                'name' => 'Test Rector',
                'email' => 'test.rector@demo.map.ac.in',
                'phone' => '8888888882', // Rector
                'kind' => 'Rector',
                'role' => 'Rector',
            ],
            [
                'name' => 'Test Warden',
                'email' => 'test.warden@demo.map.ac.in',
                'phone' => '8888888883', // Warden
                'kind' => 'Warden',
                'role' => 'Warden',
            ],
            [
                'name' => 'Test Guard',
                'email' => 'test.guard@demo.map.ac.in',
                'phone' => '8888888884', // Guard
                'kind' => 'Guard',
                'role' => 'Guard',
            ],
            [
                'name' => 'Test HK Supervisor',
                'email' => 'test.hk@demo.map.ac.in',
                'phone' => '8888888885', // HK Supervisor
                'kind' => 'HKSupervisor',
                'role' => 'HK Supervisor',
            ],
            [
                'name' => 'Test RM Supervisor',
                'email' => 'test.rm@demo.map.ac.in',
                'phone' => '8888888886', // RM Supervisor
                'kind' => 'RMSupervisor',
                'role' => 'RM Supervisor',
            ],
            [
                'name' => 'Test Laundry Manager',
                'email' => 'test.laundry@demo.map.ac.in',
                'phone' => '8888888887', // Laundry Manager
                'kind' => 'LaundryManager',
                'role' => 'Laundry Manager',
            ],
            [
                'name' => 'Test Sports Manager',
                'email' => 'test.sports@demo.map.ac.in',
                'phone' => '8888888889', // Sports Manager
                'kind' => 'SportsManager',
                'role' => 'Sports Manager',
            ],
        ];

        $created = 0;
        $updated = 0;

        foreach ($testUsers as $userData) {
            $user = User::firstOrNew(['phone' => $userData['phone']]);
            
            $isNew = !$user->exists;
            
            $user->fill([
                'tenant_id' => $tenant->id,
                'email' => $userData['email'],
                'name' => $userData['name'],
                'kind' => $userData['kind'],
                'is_map_staff' => true,
                'password' => Hash::make('test123'),
            ]);
            $user->save();

            // Assign role
            $role = Role::where('name', $userData['role'])->first();
            if ($role && !$user->hasRole($role->name)) {
                $user->assignRole($role);
            }

            if ($isNew) {
                $created++;
                $this->info("✅ Created: {$userData['name']} ({$userData['phone']}) - {$userData['role']}");
            } else {
                $updated++;
                $this->warn("🔄 Updated: {$userData['name']} ({$userData['phone']}) - {$userData['role']}");
            }
        }

        $this->newLine();
        $this->info("========================================");
        $this->info("Summary: {$created} created, {$updated} updated");
        $this->info("========================================");
        $this->newLine();
        
        $this->table(
            ['Role', 'Phone', 'OTP'],
            collect($testUsers)->map(fn($u) => [$u['role'], $u['phone'], '123456'])->toArray()
        );
        
        $this->newLine();
        $this->info("📱 All test users use OTP: 123456 (in test mode)");

        return 0;
    }
}


