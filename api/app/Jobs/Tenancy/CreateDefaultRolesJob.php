<?php

namespace App\Jobs\Tenancy;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Create Default Roles for New Tenant
 * 
 * This job runs after tenant database is created and migrated.
 * Seeds Spatie roles and permissions specific to this tenant.
 */
class CreateDefaultRolesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;

    /**
     * Create a new job instance.
     */
    public function __construct($tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Roles to create in tenant database
        $roles = [
            'Campus Manager',
            'Rector',
            'Warden',
            'HK Supervisor',
            'RM Supervisor',
            'Guard',
            'Laundry Manager',
            'Sports Manager',
            'College Management',
            'Student',
        ];

        // Create roles
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        // Basic permissions (can be expanded)
        $permissions = [
            'view-students',
            'manage-outpasses',
            'view-gate-entries',
            'manage-attendance',
            'view-reports',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }

        // Assign permissions to roles (example)
        $campusManager = Role::findByName('Campus Manager');
        $campusManager->givePermissionTo($permissions);

        \Log::info("Created default roles and permissions for tenant: {$this->tenant->code}");
    }
}

