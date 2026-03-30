<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DevPanelBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure at least one active tenant has all addons enabled
        $tenant = Tenant::query()->first();
        if ($tenant) {
            $tenant->update([
                'addon_security' => true,
                'addon_sports' => true,
                'addon_laundry' => true,
                'status' => 'active',
            ]);
        }

        // Ensure Campus Manager and Rector roles exist and have broad permissions
        $roles = ['Campus Manager', 'Rector', 'College Management'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
        $all = Permission::all();
        foreach ($roles as $roleName) {
            $role = Role::findByName($roleName);
            $role->syncPermissions($all);
        }

        // Grant seeded users those roles if present
        if ($tenant) {
            $cm = User::where('tenant_id', $tenant->id)->whereIn('kind', ['CampusManager','campus_manager'])->first();
            if ($cm && !$cm->hasRole('Campus Manager')) {
                $cm->assignRole('Campus Manager');
            }
            $rector = User::where('tenant_id', $tenant->id)->whereIn('kind', ['Rector','rector'])->first();
            if ($rector && !$rector->hasRole('Rector')) {
                $rector->assignRole('Rector');
            }
        }
    }
}


