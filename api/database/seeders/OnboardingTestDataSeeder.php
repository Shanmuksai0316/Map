<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Illuminate\Support\Facades\Artisan;

class OnboardingTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds for onboarding wizard testing.
     * This creates amenities and Super Admin user for testing.
     */
    public function run(): void
    {
        // Use the tenant that was already created (id=1) or create a new one
        $tenant = Tenant::query()->find(1);

        if (! $tenant) {
            DB::table('tenants')->insert([
                'id' => 1,
                'code' => 'onb-test',
                'name' => 'Onboarding Test College',
                'addon_security' => true,
                'addon_sports' => true,
                'addon_laundry' => true,
                'settings' => json_encode([]),
                'data' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $tenant = Tenant::query()->find(1);
        }

        if ($tenant instanceof TenantWithDatabase) {
            $manager = $tenant->database()->manager();

            if (! $manager->databaseExists($tenant)) {
                $manager->createDatabase($tenant);
            }

            Artisan::call('tenants:migrate', [
                '--tenants' => [$tenant->getTenantKey()],
            ]);
        }

        tenancy()->initialize($tenant);

        try {
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
        } finally {
            tenancy()->end();
        }

        $this->command->info('✓ Created amenities for onboarding-test tenant');

        $superAdminRole = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ]);

        $superAdmin = User::firstOrCreate(
            ['phone' => '9999999999'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Super Admin',
                'email' => 'superadmin@map.local',
                'kind' => 'SuperAdmin',
                'password' => bcrypt('password'),
            ]
        );

        $superAdmin->assignRole($superAdminRole);

        $this->command->info('✓ Created Super Admin user');
        $this->command->info('   Email: superadmin@map.local');
        $this->command->info('   Password: password');
    }
}
