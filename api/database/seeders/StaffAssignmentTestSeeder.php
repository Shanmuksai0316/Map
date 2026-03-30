<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Campus;
use App\Models\Hostel;
use App\Models\User;
use App\Services\StaffAssignmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class StaffAssignmentTestSeeder extends Seeder
{
    private StaffAssignmentService $assignmentService;
    private array $tenants = [];
    private array $hostels = [];
    private array $staff = [];

    public function run(): void
    {
        $this->assignmentService = app(StaffAssignmentService::class);
        
        $this->command->info('🧪 Seeding Staff Assignment Test Data...');
        $this->command->newLine();

        $this->seedRoles();
        $this->seedTenants();
        $this->seedCampusesAndHostels();
        $this->seedStaff();
        $this->seedAssignments();

        $this->command->newLine();
        $this->command->info('✅ Staff Assignment Test Data Seeded Successfully!');
        $this->command->info('📊 Summary:');
        $this->command->info("  - Tenants: " . count($this->tenants));
        $this->command->info("  - Hostels: " . count($this->hostels));
        $this->command->info("  - Staff: " . count($this->staff));
        $this->command->info("  - 5 assigned to single tenant");
        $this->command->info("  - 3 cross-tenant reassigned");
        $this->command->info("  - 2 recently reassigned");
        $this->command->info("  - 2 revoked assignments");
        $this->command->info("  - 3 unassigned staff");
    }

    private function seedRoles(): void
    {
        $roles = [
            'Super Admin',
            'Campus Manager',
            'Warden',
            'Guard',
            'HK Supervisor',
            'RM Supervisor',
            'Laundry Manager',
            'Sports Manager',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $this->command->info('✓ Roles seeded');
    }

    private function seedTenants(): void
    {
        $tenantData = [
            ['code' => 'MIT', 'name' => 'MIT College'],
            ['code' => 'IIT', 'name' => 'IIT Delhi'],
            ['code' => 'ASHOKA', 'name' => 'Ashoka University'],
            ['code' => 'BITS', 'name' => 'BITS Pilani'],
        ];

        foreach ($tenantData as $data) {
            $tenant = Tenant::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'status' => 'active',
                'addon_security' => true,
                'addon_sports' => true,
                'addon_laundry' => true,
                'settings' => [
                    'timezone' => 'Asia/Kolkata',
                ],
            ]);

            // Create domain for tenant
            $subdomain = \Illuminate\Support\Str::slug(strtolower($tenant->code));
            $domain = env('APP_ENV') === 'local' 
                ? $subdomain . '.localhost'
                : $subdomain . '.' . config('app.domain', 'yourapp.com');
            
            $tenant->domains()->create(['domain' => $domain]);

            $this->tenants[] = $tenant;
        }

        $this->command->info('✓ 4 Tenants created');
    }

    private function seedCampusesAndHostels(): void
    {
        foreach ($this->tenants as $tenant) {
            // Create 2 campuses per tenant
            for ($campusNum = 1; $campusNum <= 2; $campusNum++) {
                $campus = Campus::create([
                    'tenant_id' => $tenant->id,
                    'code' => "CAMPUS-{$campusNum}",
                    'name' => "{$tenant->name} Campus {$campusNum}",
                ]);

                // Create 3-4 hostels per campus
                $hostelCount = $campusNum === 1 ? 4 : 3;
                for ($hostelNum = 1; $hostelNum <= $hostelCount; $hostelNum++) {
                    $gender = $hostelNum % 2 === 0 ? 'female' : 'male';
                    $hostel = Hostel::create([
                        'tenant_id' => $tenant->id,
                        'campus_id' => $campus->id,
                        'code' => "H{$campusNum}-{$hostelNum}",
                        'name' => "{$tenant->code} Hostel {$campusNum}-{$hostelNum}",
                        'gender_mode' => $gender,
                        'curfew_time' => '22:00:00',
                        'overnight_enabled' => true,
                    ]);

                    $this->hostels[] = $hostel;
                }
            }
        }

        $this->command->info('✓ 8 Campuses and ' . count($this->hostels) . ' Hostels created');
    }

    private function seedStaff(): void
    {
        $staffData = [
            // 5 staff to be assigned to single tenant
            ['name' => 'John Warden', 'email' => 'john.warden@test.com', 'phone' => '+919876543210', 'role' => 'Warden', 'tenant' => 0],
            ['name' => 'Sarah Guard', 'email' => 'sarah.guard@test.com', 'phone' => '+919876543211', 'role' => 'Guard', 'tenant' => 0],
            ['name' => 'Mike Manager', 'email' => 'mike.manager@test.com', 'phone' => '+919876543212', 'role' => 'Campus Manager', 'tenant' => 0],
            ['name' => 'Lisa Supervisor', 'email' => 'lisa.super@test.com', 'phone' => '+919876543213', 'role' => 'HK Supervisor', 'tenant' => 1],
            ['name' => 'Tom Warden', 'email' => 'tom.warden@test.com', 'phone' => '+919876543214', 'role' => 'Warden', 'tenant' => 1],
            
            // 3 staff for cross-tenant reassignment
            ['name' => 'Alice Cross', 'email' => 'alice.cross@test.com', 'phone' => '+919876543215', 'role' => 'Warden', 'tenant' => 0], // Will move to tenant 1
            ['name' => 'Bob Transfer', 'email' => 'bob.transfer@test.com', 'phone' => '+919876543216', 'role' => 'Guard', 'tenant' => 1], // Will move to tenant 2
            ['name' => 'Carol Move', 'email' => 'carol.move@test.com', 'phone' => '+919876543217', 'role' => 'Campus Manager', 'tenant' => 2], // Will move to tenant 3
            
            // 2 staff for recent reassignment
            ['name' => 'Dave Recent', 'email' => 'dave.recent@test.com', 'phone' => '+919876543218', 'role' => 'Warden', 'tenant' => 2],
            ['name' => 'Eve NewAssign', 'email' => 'eve.new@test.com', 'phone' => '+919876543219', 'role' => 'Guard', 'tenant' => 2],
            
            // 2 staff for revoked assignments
            ['name' => 'Frank Revoked', 'email' => 'frank.revoked@test.com', 'phone' => '+919876543220', 'role' => 'Warden', 'tenant' => 3],
            ['name' => 'Grace Removed', 'email' => 'grace.removed@test.com', 'phone' => '+919876543221', 'role' => 'HK Supervisor', 'tenant' => 3],
            
            // 3 unassigned staff
            ['name' => 'Henry Unassigned', 'email' => 'henry.unassigned@test.com', 'phone' => '+919876543222', 'role' => 'Warden', 'tenant' => 0],
            ['name' => 'Iris NoHostel', 'email' => 'iris.nohostel@test.com', 'phone' => '+919876543223', 'role' => 'Guard', 'tenant' => 1],
            ['name' => 'Jack Waiting', 'email' => 'jack.waiting@test.com', 'phone' => '+919876543224', 'role' => 'RM Supervisor', 'tenant' => 2],
        ];

        foreach ($staffData as $data) {
            $tenant = $this->tenants[$data['tenant']];
            
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make('password123'),
                'kind' => 'staff',
            ]);

            $user->assignRole($data['role']);
            $this->staff[] = ['user' => $user, 'role' => $data['role']];
        }

        $this->command->info('✓ 15 Staff users created');
    }

    private function seedAssignments(): void
    {
        // 1. Assign 5 staff to single tenant (indices 0-4)
        $this->command->info('  → Assigning 5 staff to single tenant...');
        foreach (array_slice($this->staff, 0, 5) as $index => $staffData) {
            $user = $staffData['user'];
            $hostel = $this->getHostelForTenant($user->tenant_id, $index);
            
            $this->assignmentService->assignStaff($user, [
                'tenant_id' => $user->tenant_id,
                'hostel_id' => $hostel->id,
                'role' => $staffData['role'],
                'notes' => "Initial assignment - single tenant staff",
            ]);
        }

        // 2. Cross-tenant reassignments (indices 5-7)
        $this->command->info('  → Creating cross-tenant reassignments...');
        
        // Alice: MIT → IIT (30 days ago, then 15 days ago)
        $alice = $this->staff[5]['user'];
        $mitHostel = $this->getHostelForTenant($this->tenants[0]->id, 0);
        DB::table('staff_assignments')->insert([
            'user_id' => $alice->id,
            'tenant_id' => $this->tenants[0]->id,
            'hostel_id' => $mitHostel->id,
            'assigned_at' => now()->subDays(30),
            'assigned_by' => 1,
            'assignment_notes' => 'Initial MIT assignment',
            'revoked_at' => now()->subDays(15),
            'revocation_reason' => 'Cross-tenant reassignment to IIT Delhi',
            'revoked_by' => 1,
            'created_at' => now()->subDays(30),
            'updated_at' => now()->subDays(15),
        ]);
        
        $iitHostel = $this->getHostelForTenant($this->tenants[1]->id, 0);
        $alice->update(['tenant_id' => $this->tenants[1]->id]);
        $this->assignmentService->assignStaff($alice->fresh(), [
            'tenant_id' => $this->tenants[1]->id,
            'hostel_id' => $iitHostel->id,
            'role' => 'Campus Manager', // Promoted
            'notes' => 'Cross-tenant transfer: MIT → IIT with promotion',
        ]);
        DB::table('staff_assignments')->where('user_id', $alice->id)->whereNull('revoked_at')->update(['assigned_at' => now()->subDays(15)]);

        // Bob: IIT → ASHOKA (25 days ago, then 10 days ago)
        $bob = $this->staff[6]['user'];
        $iitHostel2 = $this->getHostelForTenant($this->tenants[1]->id, 1);
        DB::table('staff_assignments')->insert([
            'user_id' => $bob->id,
            'tenant_id' => $this->tenants[1]->id,
            'hostel_id' => $iitHostel2->id,
            'assigned_at' => now()->subDays(25),
            'assigned_by' => 1,
            'assignment_notes' => 'Initial IIT assignment',
            'revoked_at' => now()->subDays(10),
            'revocation_reason' => 'Transferred to ASHOKA University',
            'revoked_by' => 1,
            'created_at' => now()->subDays(25),
            'updated_at' => now()->subDays(10),
        ]);
        
        $ashokaHostel = $this->getHostelForTenant($this->tenants[2]->id, 0);
        $bob->update(['tenant_id' => $this->tenants[2]->id]);
        $this->assignmentService->assignStaff($bob->fresh(), [
            'tenant_id' => $this->tenants[2]->id,
            'hostel_id' => $ashokaHostel->id,
            'role' => 'Guard',
            'notes' => 'Cross-tenant transfer: IIT → ASHOKA',
        ]);
        DB::table('staff_assignments')->where('user_id', $bob->id)->whereNull('revoked_at')->update(['assigned_at' => now()->subDays(10)]);

        // Carol: ASHOKA → BITS (20 days ago, then 5 days ago)
        $carol = $this->staff[7]['user'];
        $ashokaHostel2 = $this->getHostelForTenant($this->tenants[2]->id, 1);
        DB::table('staff_assignments')->insert([
            'user_id' => $carol->id,
            'tenant_id' => $this->tenants[2]->id,
            'hostel_id' => $ashokaHostel2->id,
            'assigned_at' => now()->subDays(20),
            'assigned_by' => 1,
            'assignment_notes' => 'Initial ASHOKA assignment as Campus Manager',
            'revoked_at' => now()->subDays(5),
            'revocation_reason' => 'Cross-tenant reassignment to BITS Pilani',
            'revoked_by' => 1,
            'created_at' => now()->subDays(20),
            'updated_at' => now()->subDays(5),
        ]);
        
        $bitsHostel = $this->getHostelForTenant($this->tenants[3]->id, 0);
        $carol->update(['tenant_id' => $this->tenants[3]->id]);
        $this->assignmentService->assignStaff($carol->fresh(), [
            'tenant_id' => $this->tenants[3]->id,
            'hostel_id' => $bitsHostel->id,
            'role' => 'Campus Manager',
            'notes' => 'Cross-tenant transfer: ASHOKA → BITS',
        ]);
        DB::table('staff_assignments')->where('user_id', $carol->id)->whereNull('revoked_at')->update(['assigned_at' => now()->subDays(5)]);

        // 3. Recent reassignments within same tenant (indices 8-9)
        $this->command->info('  → Creating recent reassignments...');
        
        // Dave: Reassigned within ASHOKA (7 days ago → 2 days ago)
        $dave = $this->staff[8]['user'];
        $ashokaHostel3 = $this->getHostelForTenant($this->tenants[2]->id, 2);
        DB::table('staff_assignments')->insert([
            'user_id' => $dave->id,
            'tenant_id' => $this->tenants[2]->id,
            'hostel_id' => $ashokaHostel3->id,
            'assigned_at' => now()->subDays(7),
            'assigned_by' => 1,
            'assignment_notes' => 'First assignment',
            'revoked_at' => now()->subDays(2),
            'revocation_reason' => 'Reassigned to different hostel',
            'revoked_by' => 1,
            'created_at' => now()->subDays(7),
            'updated_at' => now()->subDays(2),
        ]);
        
        $ashokaHostel4 = $this->getHostelForTenant($this->tenants[2]->id, 3);
        $this->assignmentService->assignStaff($dave, [
            'tenant_id' => $this->tenants[2]->id,
            'hostel_id' => $ashokaHostel4->id,
            'role' => 'Warden',
            'notes' => 'Reassigned to cover staff shortage',
        ]);
        DB::table('staff_assignments')->where('user_id', $dave->id)->whereNull('revoked_at')->update(['assigned_at' => now()->subDays(2)]);

        // Eve: Recently assigned (3 days ago)
        $eve = $this->staff[9]['user'];
        $ashokaHostel5 = $this->getHostelForTenant($this->tenants[2]->id, 4);
        $this->assignmentService->assignStaff($eve, [
            'tenant_id' => $this->tenants[2]->id,
            'hostel_id' => $ashokaHostel5->id,
            'role' => 'Guard',
            'notes' => 'New assignment within last week',
        ]);
        DB::table('staff_assignments')->where('user_id', $eve->id)->whereNull('revoked_at')->update(['assigned_at' => now()->subDays(3)]);

        // 4. Revoked assignments (indices 10-11)
        $this->command->info('  → Creating revoked assignments...');
        
        // Frank: Assigned then revoked
        $frank = $this->staff[10]['user'];
        $bitsHostel2 = $this->getHostelForTenant($this->tenants[3]->id, 1);
        DB::table('staff_assignments')->insert([
            'user_id' => $frank->id,
            'tenant_id' => $this->tenants[3]->id,
            'hostel_id' => $bitsHostel2->id,
            'assigned_at' => now()->subDays(15),
            'assigned_by' => 1,
            'assignment_notes' => 'Initial assignment',
            'revoked_at' => now()->subDays(5),
            'revocation_reason' => 'On leave for 2 months',
            'revoked_by' => 1,
            'created_at' => now()->subDays(15),
            'updated_at' => now()->subDays(5),
        ]);

        // Grace: Assigned then revoked
        $grace = $this->staff[11]['user'];
        $bitsHostel3 = $this->getHostelForTenant($this->tenants[3]->id, 2);
        DB::table('staff_assignments')->insert([
            'user_id' => $grace->id,
            'tenant_id' => $this->tenants[3]->id,
            'hostel_id' => $bitsHostel3->id,
            'assigned_at' => now()->subDays(20),
            'assigned_by' => 1,
            'assignment_notes' => 'Initial assignment',
            'revoked_at' => now()->subDays(8),
            'revocation_reason' => 'Resigned from position',
            'revoked_by' => 1,
            'created_at' => now()->subDays(20),
            'updated_at' => now()->subDays(8),
        ]);

        // 5. Unassigned staff (indices 12-14) - no assignments needed

        $this->command->info('✓ All assignments created');
    }

    private function getHostelForTenant(string $tenantId, int $index): Hostel
    {
        $hostels = array_filter($this->hostels, fn($h) => $h->tenant_id === $tenantId);
        $hostels = array_values($hostels);
        return $hostels[$index % count($hostels)];
    }
}

