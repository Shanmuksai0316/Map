<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminEmailPasswordSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first() ?? Tenant::factory()->create([
            'code' => 'MAP-ROOT',
            'name' => 'MAP Root Tenant',
        ]);

        $user = User::firstOrCreate([
            'email' => 'superadmin@map.local',
        ], [
            'tenant_id' => $tenant->id,
            'name' => 'Super Admin',
            'phone' => '+910000000000',
            'kind' => 'SuperAdmin',
            'password' => bcrypt('password'),
        ]);

        if (! $user->hasRole('Super Admin')) {
            $user->assignRole('Super Admin');
        }

        $this->command?->info('Super Admin ready: superadmin@map.local / password');
    }
}


