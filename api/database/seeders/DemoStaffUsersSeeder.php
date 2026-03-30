<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoStaffUsersSeeder extends Seeder
{
    /**
     * Seed demo staff users for each tenant.
     * Creates 15-20 staff per tenant with proper role assignments.
     */
    public function run(): void
    {
        $tenants = Tenant::whereIn('code', ['STXAV', 'NITKT', 'CHRUN', 'ANUN', 'DLART'])->get();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📋 Creating staff for {$tenant->name}...");
            
            $staffConfigs = $this->getStaffConfigsForTenant($tenant);
            
            foreach ($staffConfigs as $config) {
                $existing = User::where('email', $config['email'])->first();
                
                if (!$existing) {
                    $user = User::create([
                        'tenant_id' => $tenant->id,
                        'name' => $config['name'],
                        'email' => $config['email'],
                        'phone' => $config['phone'],
                        'kind' => 'staff',
                        'password' => Hash::make('Staff@123'), // Demo password
                    ]);

                    // Assign role(s)
                    foreach ($config['roles'] as $roleName) {
                        // Get or create role for web guard
                        $role = Role::firstOrCreate(
                            ['name' => $roleName, 'guard_name' => 'web']
                        );
                        $user->assignRole($role);
                    }

                    $this->command->info("  ✅ {$user->name} - {$config['roles'][0]}");
                    $totalCreated++;
                } else {
                    $this->command->warn("  ⚠️  {$config['email']} already exists, skipping...");
                }
            }
        }

        $this->command->info("\n✅ Demo staff users seeding complete!");
        $this->command->info("Total staff created: {$totalCreated}");
        $this->command->info("Demo password for all staff: Staff@123");
    }

    /**
     * Get staff configurations for a tenant
     */
    private function getStaffConfigsForTenant(Tenant $tenant): array
    {
        $subdomain = $tenant->subdomain;
        // Use tenant ID hash for unique phone base per tenant
        $phoneBase = 9800000000 + (crc32($tenant->id) % 100000000);

        $staff = [];

        // 1. Rector (1)
        $staff[] = [
            'name' => $tenant->settings['contact']['name'] ?? 'Dr. ' . ucfirst($subdomain) . ' Rector',
            'email' => "rector@{$subdomain}.edu",
            'phone' => $tenant->settings['contact']['phone'] ?? "+91{$phoneBase}",
            'roles' => ['Rector'],
        ];

        // 2. Campus Managers (2-3)
        $campusManagers = ['John', 'Sarah', 'Rahul'];
        for ($i = 0; $i < 2; $i++) {
            $staff[] = [
                'name' => "{$campusManagers[$i]} " . ucfirst($subdomain),
                'email' => "cm" . ($i + 1) . "@{$subdomain}.edu",
                'phone' => "+91" . ($phoneBase + 10 + $i),
                'roles' => ['Campus Manager'],
            ];
        }

        // 3. Wardens (3-4, one per hostel - we'll create 4)
        $wardens = ['Amit Kumar', 'Priya Sharma', 'Ravi Patel', 'Sneha Reddy'];
        for ($i = 0; $i < 4; $i++) {
            $staff[] = [
                'name' => $wardens[$i],
                'email' => "warden" . ($i + 1) . "@{$subdomain}.edu",
                'phone' => "+91" . ($phoneBase + 20 + $i),
                'roles' => ['Warden'],
            ];
        }

        // 4. Security Guards (5-6)
        $guards = ['Suresh', 'Ramesh', 'Dinesh', 'Mahesh', 'Ganesh', 'Naresh'];
        for ($i = 0; $i < 5; $i++) {
            $staff[] = [
                'name' => "{$guards[$i]} Singh",
                'email' => "security" . ($i + 1) . "@{$subdomain}.edu",
                'phone' => "+91" . ($phoneBase + 30 + $i),
                'roles' => ['Security'],
            ];
        }

        // 5. Maintenance Staff (3-4)
        $maintenance = ['Vijay', 'Ajay', 'Sanjay', 'Manoj'];
        for ($i = 0; $i < 3; $i++) {
            $staff[] = [
                'name' => "{$maintenance[$i]} Verma",
                'email' => "maint" . ($i + 1) . "@{$subdomain}.edu",
                'phone' => "+91" . ($phoneBase + 40 + $i),
                'roles' => ['Maintenance'],
            ];
        }

        // 6. Admin/College Management Staff (2-3)
        $admin = ['Anita', 'Meera', 'Kavita'];
        for ($i = 0; $i < 2; $i++) {
            $staff[] = [
                'name' => "{$admin[$i]} " . ucfirst($subdomain),
                'email' => "admin" . ($i + 1) . "@{$subdomain}.edu",
                'phone' => "+91" . ($phoneBase + 50 + $i),
                'roles' => ['College Management'],
            ];
        }

        return $staff;
    }
}

