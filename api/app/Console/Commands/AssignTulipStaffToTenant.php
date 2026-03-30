<?php

namespace App\Console\Commands;

use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class AssignTulipStaffToTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staff:assign-tulip {tenantCode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign unassigned Tulip staff (name starts with \"tulip\") to the given tenant and its first hostel';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantCode = $this->argument('tenantCode');

        $tenant = Tenant::where('code', $tenantCode)->first();
        if (!$tenant) {
            $this->error('Tenant with code ' . $tenantCode . ' not found.');
            return self::FAILURE;
        }

        $hostel = Hostel::where('tenant_id', $tenant->id)->first();
        if (!$hostel) {
            $this->error('No hostel found for tenant ' . $tenant->name . ' (' . $tenant->code . ').');
            return self::FAILURE;
        }

        $staffQuery = User::query()
            ->whereNull('tenant_id')
            ->where('kind', '!=', 'student')
            ->where('name', 'ilike', 'tulip%');

        $count = $staffQuery->count();

        if ($count === 0) {
            $this->info('No unassigned Tulip staff found.');
            return self::SUCCESS;
        }

        $this->info('Assigning ' . $count . ' Tulip staff to tenant ' . $tenant->name . ' (' . $tenant->code . ') and hostel ' . $hostel->name . '...');

        DB::transaction(function () use ($staffQuery, $tenant, $hostel) {
            $staffQuery->chunkById(100, function ($users) use ($tenant, $hostel) {
                foreach ($users as $user) {
                    // Update tenant_id
                    $user->tenant_id = $tenant->id;
                    $user->save();

                    // Derive role from name (e.g. \"tulip sports manager\" -> \"Sports Manager\")
                    $roleName = null;
                    $parts = preg_split('/\s+/', trim(str_ireplace('tulip', '', $user->name)));
                    if (!empty($parts)) {
                        $roleName = ucwords(strtolower(implode(' ', $parts)));
                    }

                    if ($roleName) {
                        // Ensure role exists and assign it
                        $role = Role::firstOrCreate(
                            ['name' => $roleName, 'guard_name' => 'web']
                        );
                        $user->syncRoles([$role->name]);
                    }

                    // Create staff assignment if not already present
                    $exists = DB::table('staff_assignments')
                        ->where('tenant_id', $tenant->id)
                        ->where('user_id', $user->id)
                        ->where('hostel_id', $hostel->id)
                        ->whereNull('revoked_at')
                        ->exists();

                    if (!$exists) {
                        DB::table('staff_assignments')->insert([
                            'tenant_id'   => $tenant->id,
                            'user_id'     => $user->id,
                            'hostel_id'   => $hostel->id,
                            'assigned_at' => now(),
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);
                    }
                }
            });
        });

        $this->info('Tulip staff assignment completed.');

        return self::SUCCESS;
    }
}


