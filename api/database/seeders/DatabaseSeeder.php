<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $tenant = Tenant::firstOrCreate([
            'code' => 'MAP-ROOT',
        ], [
            'name' => 'MAP Root Tenant',
            'addon_security' => true,
            'addon_sports' => true,
            'addon_laundry' => true,
            'settings' => [],
        ]);

        // Ensure domain exists for MAP-ROOT tenant
        if (!$tenant->domains()->exists()) {
            $tenant->domains()->create([
                'domain' => 'map-root.localhost',
            ]);
        }

        $admin = User::firstOrCreate([
            'phone' => '+910000000000',
        ], [
            'tenant_id' => $tenant->id,
            'name' => 'MAP Super Admin',
            'email' => null, // Email not needed - phone/OTP authentication
            'kind' => 'SuperAdmin',
        ]);

        $admin->assignRole('Super Admin');

        $campusManager = User::firstOrCreate([
            'phone' => '+910000000001',
        ], [
            'tenant_id' => $tenant->id,
            'name' => 'MAP Campus Manager',
            'email' => null, // Email not needed - phone/OTP authentication
            'kind' => 'CampusManager',
        ]);

        $campusManager->assignRole('Campus Manager');
    }
}
