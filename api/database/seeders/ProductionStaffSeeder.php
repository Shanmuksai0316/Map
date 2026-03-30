<?php

namespace Database\Seeders;

use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Production Staff Seeder
 * 
 * Creates all 10 staff roles with Indian names for each tenant.
 * Assigns staff to hostels where applicable.
 */
class ProductionStaffSeeder extends Seeder
{
    /**
     * Indian names for staff
     */
    private array $indianFirstNamesMale = [
        'Rajesh', 'Suresh', 'Ramesh', 'Mahesh', 'Dinesh', 'Ganesh', 'Naresh', 'Mukesh',
        'Amit', 'Ravi', 'Kumar', 'Vikram', 'Sunil', 'Deepak', 'Manoj', 'Pradeep',
        'Anil', 'Vinod', 'Sandeep', 'Rajendra', 'Vijay', 'Ajay', 'Sanjay', 'Manoj',
    ];

    private array $indianFirstNamesFemale = [
        'Priya', 'Sneha', 'Pooja', 'Kavya', 'Shreya', 'Neha', 'Divya', 'Anjali',
        'Swati', 'Rashmi', 'Sunita', 'Kavita', 'Anita', 'Geeta', 'Mamta', 'Rekha',
        'Usha', 'Radha', 'Meera', 'Kiran', 'Suman', 'Ritu', 'Jyoti', 'Manisha',
    ];

    private array $indianLastNames = [
        'Sharma', 'Verma', 'Iyer', 'Reddy', 'Menon', 'Patel', 'Singh', 'Khan',
        'Das', 'Nair', 'Gupta', 'Kulkarni', 'Bhatt', 'Chawla', 'Bose', 'Joshi',
        'Agarwal', 'Malhotra', 'Kapoor', 'Saxena', 'Mehta', 'Desai', 'Rao', 'Pillai',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('👨‍💼 Creating staff for each tenant...');

        $tenants = Tenant::all();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📋 Creating staff for {$tenant->name}...");
            
            $hostels = Hostel::where('tenant_id', $tenant->id)->get();
            
            if ($hostels->isEmpty()) {
                $this->command->warn("  ⚠️  No hostels found for {$tenant->name}, skipping staff...");
                continue;
            }

            $staffConfigs = $this->getStaffConfigsForTenant($tenant, $hostels);
            
            foreach ($staffConfigs as $config) {
                $existing = User::where('tenant_id', $tenant->id)
                    ->where('phone', $config['phone'])
                    ->first();
                
                if (!$existing) {
                    $user = User::create([
                        'tenant_id' => $tenant->id,
                        'name' => $config['name'],
                        'email' => $config['email'] ?? null,
                        'phone' => $config['phone'],
                        'kind' => 'staff',
                        'password' => Hash::make('Staff@123'), // Demo password
                        'is_map_staff' => $config['is_map_staff'] ?? false,
                    ]);

                    // Assign role(s)
                    foreach ($config['roles'] as $roleName) {
                        $role = Role::firstOrCreate(
                            ['name' => $roleName, 'guard_name' => 'web']
                        );
                        $user->assignRole($role);
                    }

                    // Assign to hostels if applicable
                    if (isset($config['hostel_ids']) && !empty($config['hostel_ids'])) {
                        foreach ($config['hostel_ids'] as $hostelId) {
                            DB::table('staff_assignments')->insert([
                                'user_id' => $user->id,
                                'hostel_id' => $hostelId,
                                'assigned_at' => now(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    $this->command->info("  ✅ {$user->name} - {$config['roles'][0]}");
                    $totalCreated++;
                } else {
                    $this->command->warn("  ⚠️  {$config['phone']} already exists, skipping...");
                }
            }
        }

        $this->command->info("\n✅ Production staff seeding complete!");
        $this->command->info("Total staff created: {$totalCreated}");
        $this->command->info("Demo password for all staff: Staff@123");
    }

    /**
     * Get staff configurations for a tenant
     */
    private function getStaffConfigsForTenant(Tenant $tenant, $hostels): array
    {
        $phoneBase = 9800000000 + (crc32($tenant->id) % 100000000);
        $staff = [];
        $hostelIds = $hostels->pluck('id')->toArray();

        // 1. Rector (1 per tenant - College representative, no hostel assignment)
        $staff[] = [
            'name' => 'Dr. ' . $this->indianName(true),
            'email' => "rector@{$tenant->subdomain}.ac.in",
            'phone' => "+91" . ($phoneBase + 1),
            'roles' => ['Rector'],
            'is_map_staff' => false,
        ];

        // 2. Campus Managers (2 per tenant - MAP staff, assigned to all hostels)
        for ($i = 0; $i < 2; $i++) {
            $staff[] = [
                'name' => $this->indianName(true),
                'email' => "cm" . ($i + 1) . "@{$tenant->subdomain}.ac.in",
                'phone' => "+91" . ($phoneBase + 10 + $i),
                'roles' => ['Campus Manager'],
                'is_map_staff' => true,
                'hostel_ids' => $hostelIds,
            ];
        }

        // 3. Wardens (1 per hostel - MAP staff)
        $wardenIndex = 0;
        foreach ($hostels as $hostel) {
            $staff[] = [
                'name' => $this->indianName($wardenIndex % 2 === 0),
                'email' => "warden" . ($wardenIndex + 1) . "@{$tenant->subdomain}.ac.in",
                'phone' => "+91" . ($phoneBase + 20 + $wardenIndex),
                'roles' => ['Warden'],
                'is_map_staff' => true,
                'hostel_ids' => [$hostel->id],
            ];
            $wardenIndex++;
        }

        // 4. Guards (2 per hostel - MAP staff)
        $guardIndex = 0;
        foreach ($hostels as $hostel) {
            for ($i = 0; $i < 2; $i++) {
                $staff[] = [
                    'name' => $this->indianName(true) . ' Singh',
                    'email' => "guard" . ($guardIndex + 1) . "@{$tenant->subdomain}.ac.in",
                    'phone' => "+91" . ($phoneBase + 30 + $guardIndex),
                    'roles' => ['Guard'],
                    'is_map_staff' => true,
                    'hostel_ids' => [$hostel->id],
                ];
                $guardIndex++;
            }
        }

        // 5. HK Supervisors (1 per hostel - MAP staff)
        $hkIndex = 0;
        foreach ($hostels as $hostel) {
            $staff[] = [
                'name' => $this->indianName(false),
                'email' => "hk" . ($hkIndex + 1) . "@{$tenant->subdomain}.ac.in",
                'phone' => "+91" . ($phoneBase + 50 + $hkIndex),
                'roles' => ['HK Supervisor'],
                'is_map_staff' => true,
                'hostel_ids' => [$hostel->id],
            ];
            $hkIndex++;
        }

        // 6. RM Supervisors (1 per hostel - MAP staff)
        $rmIndex = 0;
        foreach ($hostels as $hostel) {
            $staff[] = [
                'name' => $this->indianName(true),
                'email' => "rm" . ($rmIndex + 1) . "@{$tenant->subdomain}.ac.in",
                'phone' => "+91" . ($phoneBase + 60 + $rmIndex),
                'roles' => ['RM Supervisor'],
                'is_map_staff' => true,
                'hostel_ids' => [$hostel->id],
            ];
            $rmIndex++;
        }

        // 7. Laundry Managers (1 per tenant if laundry addon enabled - MAP staff)
        if ($tenant->addon_laundry) {
            $staff[] = [
                'name' => $this->indianName(false),
                'email' => "laundry@{$tenant->subdomain}.ac.in",
                'phone' => "+91" . ($phoneBase + 70),
                'roles' => ['Laundry Manager'],
                'is_map_staff' => true,
                'hostel_ids' => $hostelIds,
            ];
        }

        // 8. Sports Managers (1 per tenant if sports addon enabled - MAP staff)
        if ($tenant->addon_sports) {
            $staff[] = [
                'name' => $this->indianName(true),
                'email' => "sports@{$tenant->subdomain}.ac.in",
                'phone' => "+91" . ($phoneBase + 80),
                'roles' => ['Sports Manager'],
                'is_map_staff' => true,
                'hostel_ids' => $hostelIds,
            ];
        }

        // 9. College Management (2 per tenant - College representative, no hostel assignment)
        for ($i = 0; $i < 2; $i++) {
            $staff[] = [
                'name' => $this->indianName($i % 2 === 0),
                'email' => "college" . ($i + 1) . "@{$tenant->subdomain}.ac.in",
                'phone' => "+91" . ($phoneBase + 90 + $i),
                'roles' => ['College Management'],
                'is_map_staff' => false,
            ];
        }

        return $staff;
    }

    /**
     * Generate Indian name
     */
    private function indianName(bool $male = true): string
    {
        $first = $male 
            ? $this->indianFirstNamesMale[array_rand($this->indianFirstNamesMale)]
            : $this->indianFirstNamesFemale[array_rand($this->indianFirstNamesFemale)];
        $last = $this->indianLastNames[array_rand($this->indianLastNames)];

        return "{$first} {$last}";
    }
}

