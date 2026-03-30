<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class PlaywrightSeeder extends Seeder
{
    /**
     * Playwright-compatible seeder that works around PostgreSQL transaction limitations
     */
    public function run(): void
    {
        // Create tenants (central database only)
        // NOTE: Tenant codes MUST have MAP- prefix due to tenants_code_map_prefix_chk constraint
        $tenants = collect([
            ['name' => 'Saraswati Institute of Technology', 'code' => 'MAP-SIT'],
            ['name' => 'Nalanda University (West Campus)', 'code' => 'MAP-NUW'],
            ['name' => 'Vidya Bharati College', 'code' => 'MAP-VBC'],
        ])->map(function ($t) {
            return Tenant::firstOrCreate(['code' => $t['code']], ['name' => $t['name']]);
        });

        // Create basic users for each tenant (central database only)
        foreach ($tenants as $tenant) {
            $this->createBasicUsers($tenant);
        }
    }

    /**
     * Create basic users for a tenant (central database only)
     */
    private function createBasicUsers(Tenant $tenant): void
    {
        $users = [
            ['name' => 'Campus Manager', 'phone' => '+919876543210', 'role' => 'campus_manager'],
            ['name' => 'Rector', 'phone' => '+919876543211', 'role' => 'rector'],
            ['name' => 'Warden', 'phone' => '+919876543212', 'role' => 'warden'],
            ['name' => 'Guard', 'phone' => '+919876543213', 'role' => 'guard'],
            ['name' => 'Test Student', 'phone' => '+919876543214', 'role' => 'student'],
        ];

        foreach ($users as $userData) {
            // Make phone numbers unique per tenant
            $phone = $userData['phone'] . $tenant->id;
            
            // Use raw SQL to avoid transaction issues
            $userId = DB::table('users')->insertGetId([
                'tenant_id' => $tenant->id,
                'phone' => $phone,
                'name' => $userData['name'],
                'email' => strtolower(str_replace(' ', '.', $userData['name'])) . '@' . $tenant->code . '.edu',
                'kind' => $userData['role'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign role
            DB::table('user_roles')->insert([
                'user_id' => $userId,
                'role' => $userData['role'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
