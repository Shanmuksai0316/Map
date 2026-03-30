<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StaffAssignmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ComprehensiveDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Creating comprehensive dummy data for 4 tenants...');
        $this->command->newLine();

        // Seed roles first
        $this->seedRoles();

        $tenantData = [
            [
                'name' => 'Ashoka University',
                'code' => 'ASHOKA',
                'campuses' => [
                    [
                        'name' => 'Main Campus', 
                        'code' => 'MAIN',
                        'hostels' => [
                            ['name' => 'Vedanta Hall', 'code' => 'VH-01', 'gender' => 'boys', 'floors' => 3],
                            ['name' => 'Shakti Hall', 'code' => 'SH-01', 'gender' => 'girls', 'floors' => 3],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Jawaharlal Nehru University',
                'code' => 'JNU',
                'campuses' => [
                    [
                        'name' => 'North Campus', 
                        'code' => 'NORTH',
                        'hostels' => [
                            ['name' => 'Ganga Hostel', 'code' => 'GH-01', 'gender' => 'boys', 'floors' => 4],
                            ['name' => 'Yamuna Hostel', 'code' => 'YH-01', 'gender' => 'girls', 'floors' => 4],
                        ],
                    ],
                    [
                        'name' => 'South Campus', 
                        'code' => 'SOUTH',
                        'hostels' => [
                            ['name' => 'Kaveri Hostel', 'code' => 'KV-01', 'gender' => 'boys', 'floors' => 3],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Indian Institute of Technology Delhi',
                'code' => 'IITD',
                'campuses' => [
                    [
                        'name' => 'Main Campus', 
                        'code' => 'MAIN',
                        'hostels' => [
                            ['name' => 'Kailash Hostel', 'code' => 'KH-01', 'gender' => 'boys', 'floors' => 5],
                            ['name' => 'Aravali Hostel', 'code' => 'AH-01', 'gender' => 'girls', 'floors' => 5],
                            ['name' => 'Nilgiri Hostel', 'code' => 'NH-01', 'gender' => 'coed', 'floors' => 4],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Delhi University',
                'code' => 'DU',
                'campuses' => [
                    [
                        'name' => 'North Campus', 
                        'code' => 'NORTH',
                        'hostels' => [
                            ['name' => 'Gwyer Hall', 'code' => 'GWH-01', 'gender' => 'girls', 'floors' => 3],
                        ],
                    ],
                ],
            ],
        ];

        $tenants = [];
        foreach ($tenantData as $index => $data) {
            $tenants[] = $this->createTenant($data, $index + 1);
        }

        $this->command->newLine();
        
        // Create Super Admin user (uses first tenant)
        if (!empty($tenants)) {
            $firstTenant = $tenants[0];
            $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
            $user = User::firstOrCreate(
                ['phone' => '9999999999'],
                [
                    'tenant_id' => $firstTenant->id,
                    'name' => 'Super Admin',
                    'email' => 'superadmin@map.local',
                    'kind' => 'SuperAdmin',
                    'password' => Hash::make('password'),
                ]
            );
            $user->assignRole($role);
            $this->command->info('✓ Super Admin created');
        }
        
        // Seed comprehensive staff assignments after all tenants are created
        $this->seedComprehensiveStaffAssignments($tenants);
        
        $this->command->newLine();
        $this->command->info('✅ Comprehensive dummy data created successfully!');
        $this->command->info('✅ 4 tenants, multiple campuses, hostels, rooms, staff, and students');
        $this->command->info('✅ 50+ staff with comprehensive assignments');
        $this->command->info('✅ Super Admin: superadmin@map.local / password');
    }

    private function seedRoles(): void
    {
        $roles = [
            'Super Admin', 'Rector', 'Campus Manager', 'Warden', 
            'Guard', 'Laundry Manager', 'Sports Manager', 'HK Supervisor'
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->command->info('✓ Roles seeded');
    }

    private function createTenant(array $data, int $sequence): Tenant
    {
        $this->command->info("Creating tenant: {$data['name']}");

        // Create tenant in central database
        $tenant = Tenant::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'addon_security' => true,
            'addon_sports' => true,
            'addon_laundry' => true,
            'settings' => [
                'timezone' => 'Asia/Kolkata',
                'branding' => ['primary_color' => '#1E56D9'],
            ],
        ]);

        // Create domain for tenant
        $subdomain = \Illuminate\Support\Str::slug(strtolower($tenant->code));
        $domain = env('APP_ENV') === 'local' 
            ? $subdomain . '.localhost'
            : $subdomain . '.' . config('app.domain', 'yourapp.com');
        
        $tenant->domains()->create(['domain' => $domain]);

        // Create tenant database
        try {
            $tenant->database()->manager()->createDatabase($tenant);
        } catch (\Exception $e) {
            // Database might already exist - that's ok, we'll reset it
            if (str_contains($e->getMessage(), 'already exists')) {
                // Drop and recreate
                try {
                    $tenant->database()->manager()->deleteDatabase($tenant);
                    $tenant->database()->manager()->createDatabase($tenant);
                } catch (\Exception $e2) {
                    $this->command->warn("  Database recreation failed: {$e2->getMessage()}");
                }
            }
        }
        
        // Run tenant migrations
        // Migrations are now run once on central database - no need for per-tenant migrations
        // All tenant data is in single shared database with tenant_id

        // Set tenant context for filesystem/queue/cache isolation (no DB switching needed)
        tenancy()->initialize($tenant);

        try {
            // Seed amenities in central database (all data is in single shared database)
            $this->seedAmenities();

            // Create campuses and hostels - all data is in central database with tenant_id
            foreach ($data['campuses'] as $campusData) {
                $campus = Campus::create([
                    'tenant_id' => $tenant->id, // Explicitly set tenant_id
                    'name' => $campusData['name'],
                    'code' => $campusData['code'],
                    'address' => [
                        'street' => '123 Campus Road',
                        'city' => 'Delhi',
                        'state' => 'Delhi',
                        'pincode' => '110001',
                        'country' => 'India',
                    ],
                ]);

                $this->command->info("  ✓ Campus: {$campus->name}");

                // Create hostels for this campus
                $hostelsData = $campusData['hostels'] ?? [];
                foreach ($hostelsData as $hostelData) {
                    $hostel = $this->createHostel($tenant, $campus, $hostelData);
                    
                    // Create rooms
                    $roomsCreated = $this->createRooms($tenant, $campus, $hostel, $hostelData['floors']);
                    
                    // Assign amenities
                    $amenities = Amenity::limit(5)->pluck('id');
                    foreach ($amenities as $amenityId) {
                        DB::table('hostel_amenities')->insert([
                            'hostel_id' => $hostel->id,
                            'amenity_id' => $amenityId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    
                    // Assign modules
                    $modules = ['security', 'sports'];
                    foreach ($modules as $module) {
                        DB::table('hostel_modules')->insert([
                            'hostel_id' => $hostel->id,
                            'module_key' => $module,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $this->command->info("    ✓ Hostel: {$hostel->name} ({$roomsCreated} rooms)");
                }
            }

            // Create staff users
            $this->createStaff($tenant);
            
            // Create students
            $this->createStudents($tenant);

        } finally {
            tenancy()->end();
        }

        $this->command->info("  ✅ Tenant {$data['code']} complete");
        $this->command->newLine();
        
        return $tenant;
    }

    private function seedAmenities(): void
    {
        $amenities = [
            ['key' => 'wifi', 'label' => 'WiFi'],
            ['key' => 'gym', 'label' => 'Gym'],
            ['key' => 'laundry', 'label' => 'Laundry'],
            ['key' => 'ac', 'label' => 'Air Conditioning'],
            ['key' => 'mess', 'label' => 'Mess/Canteen'],
            ['key' => 'study_room', 'label' => 'Study Room'],
            ['key' => 'common_room', 'label' => 'Common Room'],
            ['key' => 'security', 'label' => '24/7 Security'],
            ['key' => 'parking', 'label' => 'Parking'],
            ['key' => 'medical', 'label' => 'Medical Room'],
        ];

        foreach ($amenities as $amenity) {
            Amenity::firstOrCreate(['key' => $amenity['key']], $amenity);
        }
    }

    private function createHostel(Tenant $tenant, Campus $campus, array $data): Hostel
    {
        return Hostel::create([
            'tenant_id' => $tenant->id, // Explicitly set tenant_id
            'campus_id' => $campus->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'gender_mode' => $data['gender'],
            'curfew_time' => '22:00:00',
            'overnight_enabled' => true,
            'visiting_start' => '09:00:00',
            'visiting_end' => '18:00:00',
        ]);
    }

    private function createRooms(Tenant $tenant, Campus $campus, Hostel $hostel, int $floors): int
    {
        $roomsCreated = 0;
        $roomTypes = ['Single', 'Double', 'Suite'];

        for ($floor = 1; $floor <= $floors; $floor++) {
            $roomsPerFloor = rand(8, 12);
            $roomType = $roomTypes[$floor % 3];

            for ($room = 1; $room <= $roomsPerFloor; $room++) {
                Room::create([
                    'tenant_id' => $tenant->id, // Explicitly set tenant_id
                    'campus_id' => $campus->id,
                    'hostel_id' => $hostel->id,
                    'floor_code' => (string)$floor,
                    'number' => $floor . '-' . str_pad((string)$room, 3, '0', STR_PAD_LEFT),
                    'capacity' => $roomType === 'Single' ? 1 : ($roomType === 'Double' ? 2 : 3),
                    'room_type' => $roomType,
                    'is_active' => true,
                ]);
                $roomsCreated++;
            }
        }

        return $roomsCreated;
    }

    private function createStaff(Tenant $tenant): void
    {
        // Generate unique phone numbers based on tenant code hash
        $basePhone = hexdec(substr(md5($tenant->code), 0, 4)) % 100000;
        
        $staffRoles = [
            ['name' => 'Campus Manager', 'kind' => 'CampusManager'],
            ['name' => 'Warden North', 'kind' => 'Warden'],
            ['name' => 'Guard Main Gate', 'kind' => 'Guard'],
            ['name' => 'Laundry Manager', 'kind' => 'LaundryManager'],
        ];

        foreach ($staffRoles as $index => $staff) {
            $phone = '91' . str_pad((string)($basePhone + $index), 8, '0', STR_PAD_LEFT);
            
            $user = User::create([
                'tenant_id' => $tenant->id, // Explicitly set tenant_id
                'name' => $staff['name'],
                'phone' => $phone,
                'email' => strtolower(str_replace(' ', '.', $staff['name'])) . '@' . $tenant->code . '.local',
                'password' => Hash::make('password'),
                'kind' => $staff['kind'],
            ]);

            $roleName = explode(' ', $staff['name'])[0] === 'Campus' ? 'Campus Manager' : 
                        (explode(' ', $staff['name'])[0] === 'Laundry' ? 'Laundry Manager' : 
                        explode(' ', $staff['name'])[0]);
            
            $role = Role::firstOrCreate(['name' => $roleName]);
            $user->assignRole($role);
        }

        $this->command->info("    ✓ Staff: 4 users created");
    }

    private function createStudents(Tenant $tenant): void
    {
        $hostels = Hostel::limit(2)->get();
        
        if ($hostels->isEmpty()) {
            return;
        }

        // Generate unique base ID from tenant code
        $baseId = hexdec(substr(md5($tenant->code), 0, 6)) % 1000000;

        $studentCount = 1; // Start from 1
        foreach ($hostels as $hostel) {
            for ($i = 1; $i <= 5; $i++) {
                $uniqueId = $baseId + $studentCount;
                $phone = '9' . str_pad((string)(300000000 + $uniqueId), 9, '0', STR_PAD_LEFT);
                
                $user = User::create([
                    'tenant_id' => $tenant->id, // Explicitly set tenant_id
                    'name' => "{$tenant->code} Student {$studentCount}",
                    'phone' => $phone,
                    'email' => "student{$uniqueId}@{$tenant->code}.local",
                    'password' => Hash::make('password'),
                    'kind' => 'Student',
                ]);

                Student::create([
                    'tenant_id' => $tenant->id, // Explicitly set tenant_id
                    'user_id' => $user->id,
                    'hostel_id' => $hostel->id,
                    'map_student_id' => 'STD-' . $tenant->code . '-' . str_pad((string)$studentCount, 4, '0', STR_PAD_LEFT),
                    'student_uid' => $tenant->code . '-UID' . str_pad((string)$studentCount, 3, '0', STR_PAD_LEFT),
                    'roll_no' => '2024' . str_pad((string)$uniqueId, 4, '0', STR_PAD_LEFT),
                    'program' => 'B.Tech',
                    'year_of_study' => rand(1, 4),
                    'admission_year' => 2024,
                    'guardian' => [
                        'name' => 'Guardian ' . $studentCount,
                        'phone' => '98' . str_pad((string)$uniqueId, 8, '0', STR_PAD_LEFT),
                    ],
                ]);

                $studentCount++;
            }
        }

        $this->command->info("    ✓ Students: " . ($studentCount - 1) . " students created");
    }

    private function seedComprehensiveStaffAssignments(array $tenants): void
    {
        if (empty($tenants)) {
            return;
        }

        $this->command->newLine();
        $this->command->info('🔄 Seeding comprehensive staff assignments...');

        $assignmentService = app(StaffAssignmentService::class);
        $additionalStaffCreated = 0;
        $assignmentsCreated = 0;

        // For each tenant, create additional staff and assign them
        foreach ($tenants as $index => $tenant) {
            $hostels = Hostel::where('tenant_id', $tenant->id)->get();
            
            if ($hostels->isEmpty()) {
                continue;
            }

            $this->command->info("  → {$tenant->name}: Creating additional staff");

            // Create 5-7 additional staff per tenant
            $staffCount = rand(5, 7);
            $roles = ['Warden', 'Guard', 'HK Supervisor', 'RM Supervisor', 'Laundry Manager', 'Sports Manager'];
            
            for ($i = 0; $i < $staffCount; $i++) {
                $role = $roles[array_rand($roles)];
                $basePhone = hexdec(substr(md5($tenant->code . $i), 0, 4)) % 100000;
                $phone = '91' . str_pad((string)($basePhone + 1000 + $i), 8, '0', STR_PAD_LEFT);
                
                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'name' => "{$role} {$tenant->code}-{$i}",
                    'phone' => $phone,
                    'email' => strtolower($role) . ".{$i}@{$tenant->code}.local",
                    'password' => Hash::make('password'),
                    'kind' => 'staff',
                ]);

                $user->assignRole($role);
                $additionalStaffCreated++;

                // Assign to random hostel (60% chance)
                if (rand(1, 100) <= 60) {
                    $hostel = $hostels->random();
                    
                    try {
                        $assignmentService->assignStaff($user, [
                            'tenant_id' => $tenant->id,
                            'hostel_id' => $hostel->id,
                            'role' => $role,
                            'notes' => "Auto-assigned during comprehensive seeding",
                        ]);
                        $assignmentsCreated++;
                    } catch (\Exception $e) {
                        $this->command->warn("    Failed to assign {$user->name}: " . $e->getMessage());
                    }
                }
            }

            $this->command->info("    ✓ Created {$staffCount} additional staff");
        }

        // Create cross-tenant reassignments (5 staff)
        $this->command->info('  → Creating cross-tenant reassignments');
        
        // Get some assigned staff from first 3 tenants
        for ($i = 0; $i < 3 && $i < count($tenants); $i++) {
            $sourceTenant = $tenants[$i];
            $targetTenant = $tenants[($i + 1) % count($tenants)];
            
            // Find staff assigned to source tenant
            $staff = User::where('tenant_id', $sourceTenant->id)
                ->where('kind', 'staff')
                ->whereHas('staffHostels')
                ->first();
            
            if ($staff) {
                $targetHostels = Hostel::where('tenant_id', $targetTenant->id)->get();
                if ($targetHostels->isNotEmpty()) {
                    $targetHostel = $targetHostels->random();
                    
                    // Create historical assignment first
                    $currentAssignment = DB::table('staff_assignments')
                        ->where('user_id', $staff->id)
                        ->whereNull('revoked_at')
                        ->first();
                    
                    if ($currentAssignment) {
                        // Revoke current assignment
                        DB::table('staff_assignments')
                            ->where('id', $currentAssignment->id)
                            ->update([
                                'revoked_at' => now()->subDays(rand(10, 30)),
                                'revocation_reason' => "Cross-tenant transfer: {$sourceTenant->name} → {$targetTenant->name}",
                                'revoked_by' => 1,
                            ]);
                        
                        // Update user tenant
                        $staff->update(['tenant_id' => $targetTenant->id]);
                        
                        // Create new assignment
                        try {
                            $assignmentService->assignStaff($staff->fresh(), [
                                'tenant_id' => $targetTenant->id,
                                'hostel_id' => $targetHostel->id,
                                'role' => $staff->roles->first()->name ?? 'Warden',
                                'notes' => "Cross-tenant transfer from {$sourceTenant->name}",
                            ]);
                            
                            // Backdate the assignment
                            DB::table('staff_assignments')
                                ->where('user_id', $staff->id)
                                ->whereNull('revoked_at')
                                ->update(['assigned_at' => now()->subDays(rand(5, 15))]);
                            
                            $assignmentsCreated++;
                            $this->command->info("    ✓ Transferred {$staff->name}: {$sourceTenant->name} → {$targetTenant->name}");
                        } catch (\Exception $e) {
                            $this->command->warn("    Failed cross-tenant transfer: " . $e->getMessage());
                        }
                    }
                }
            }
        }

        // Create some revoked assignments
        $this->command->info('  → Creating revoked assignments');
        
        $staffWithAssignments = User::where('kind', 'staff')
            ->whereHas('staffHostels')
            ->take(3)
            ->get();
        
        foreach ($staffWithAssignments as $staff) {
            try {
                $assignmentService->revokeAssignment($staff, 'On leave / Resigned - auto-generated during seeding');
                $this->command->info("    ✓ Revoked assignment for {$staff->name}");
            } catch (\Exception $e) {
                $this->command->warn("    Failed to revoke: " . $e->getMessage());
            }
        }

        $this->command->newLine();
        $this->command->info("✅ Staff Assignment Summary:");
        $this->command->info("  - Additional staff created: {$additionalStaffCreated}");
        $this->command->info("  - Total assignments: {$assignmentsCreated}");
        $this->command->info("  - Cross-tenant transfers: 3");
        $this->command->info("  - Revoked assignments: 3");
    }
}

