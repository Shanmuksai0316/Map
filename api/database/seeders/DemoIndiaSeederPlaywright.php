<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoIndiaSeederPlaywright extends Seeder
{
    /**
     * Playwright-compatible version of DemoIndiaSeeder that properly handles tenant database creation
     * This version runs seeding outside of transactions to allow CREATE DATABASE commands
     */
    public function run(): void
    {
        // Disable database transactions for this seeder to allow CREATE DATABASE
        DB::transaction(function () {
            $this->runSeeding();
        });
    }

    /**
     * Run the actual seeding logic
     */
    private function runSeeding(): void
    {
        // Create test tenants
        $tenants = collect([
            ['name' => 'Saraswati Institute of Technology', 'code' => 'MAP-SIT'],
            ['name' => 'Nalanda University (West Campus)', 'code' => 'NUW'],
            ['name' => 'Vidya Bharati College', 'code' => 'VBC'],
        ])->map(function ($t) {
            return Tenant::firstOrCreate(['code' => $t['code']], ['name' => $t['name']]);
        });

        // Seed each tenant with full data
        foreach ($tenants as $tenant) {
            $this->seedTenant($tenant);
        }
    }

    /**
     * Seed a tenant with full data including tenant database creation
     */
    private function seedTenant(Tenant $tenant): void
    {
        // Initialize tenancy for this tenant
        tenancy()->initialize($tenant);

        // Create campus for this tenant
        $campus = \App\Models\Campus::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Campus', 'address' => ['city' => 'Pune', 'state' => 'Maharashtra']]
        );

        // Create hostels
        $hostels = [
            \App\Models\Hostel::firstOrCreate(
                ['campus_id' => $campus->id, 'code' => 'H1'],
                ['name' => 'H1 Boys Hostel', 'gender_mode' => 'male', 'curfew_time' => '22:00', 'overnight_enabled' => true]
            ),
            \App\Models\Hostel::firstOrCreate(
                ['campus_id' => $campus->id, 'code' => 'H2'],
                ['name' => 'H2 Girls Hostel', 'gender_mode' => 'female', 'curfew_time' => '21:00', 'overnight_enabled' => true]
            ),
        ];

        // Create rooms and beds
        foreach ($hostels as $hostel) {
            for ($r = 101; $r < 141; $r++) {
                $room = \App\Models\Room::firstOrCreate([
                    'campus_id' => $campus->id, 'hostel_id' => $hostel->id, 'number' => (string) $r,
                ], [
                    'capacity' => 3, 'is_active' => true,
                ]);
                foreach (['A', 'B', 'C'] as $suf) {
                    \App\Models\RoomBed::firstOrCreate([
                        'hostel_id' => $hostel->id, 'room_id' => $room->id, 'code' => $r.$suf,
                    ]);
                }
            }
        }

        // Create users for this tenant
        $this->createTenantUsers($tenant);

        // Create some test data
        $this->createTestData($tenant, $hostels);
    }

    /**
     * Create users for a tenant
     */
    private function createTenantUsers(Tenant $tenant): void
    {
        $users = [
            ['name' => 'Campus Manager', 'phone' => '+919876543210', 'role' => 'campus_manager'],
            ['name' => 'Rector', 'phone' => '+919876543211', 'role' => 'rector'],
            ['name' => 'Warden', 'phone' => '+919876543212', 'role' => 'warden'],
            ['name' => 'Guard', 'phone' => '+919876543213', 'role' => 'guard'],
            ['name' => 'Test Student', 'phone' => '+919876543214', 'role' => 'student'],
        ];

        foreach ($users as $userData) {
            $user = \App\Models\User::firstOrCreate(
                ['phone' => $userData['phone']],
                [
                    'name' => $userData['name'],
                    'email' => strtolower(str_replace(' ', '.', $userData['name'])) . '@' . $tenant->code . '.edu',
                    'kind' => $userData['role'],
                ]
            );
            $user->assignRole($userData['role']);
        }
    }

    /**
     * Create test data for the tenant
     */
    private function createTestData(Tenant $tenant, $hostels): void
    {
        // Create some outpasses
        $today = now();
        foreach ($hostels as $hostel) {
            for ($i = 0; $i < 5; $i++) {
                \App\Models\OutPass::firstOrCreate([
                    'student_id' => 1, // Assuming student with ID 1 exists
                    'requested_at' => $today,
                ], [
                    'hostel_id' => $hostel->id,
                    'reason' => 'Medical appointment',
                    'requested_until' => $today->copy()->addHours(4),
                    'status' => 'pending',
                ]);
            }
        }

        // Create some notices
        for ($i = 0; $i < 3; $i++) {
            \App\Models\Notice::firstOrCreate([
                'title' => "Test Notice {$i}",
                'content' => "This is test notice content {$i}",
            ], [
                'status' => 'published',
                'published_at' => now(),
            ]);
        }
    }
}


