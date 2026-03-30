<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoIndiaSeederTest extends Seeder
{
    /**
     * Test-friendly version of DemoIndiaSeeder that works with multi-tenant architecture
     * This version only creates central database data (tenants, users) and skips tenant-specific data
     */
    public function run(): void
    {
        // Disable tenant database creation during seeding
        $this->disableTenantDatabaseCreation();

        // Create roles first
        $this->createRoles();

        // Create test tenants (central database only)
        $tenants = collect([
            ['name' => 'Saraswati Institute of Technology', 'code' => 'SIT'],
            ['name' => 'Nalanda University (West Campus)', 'code' => 'NUW'],
            ['name' => 'Vidya Bharati College', 'code' => 'VBC'],
        ])->map(function ($t) {
            return Tenant::firstOrCreate(['code' => $t['code']], ['name' => $t['name']]);
        });

        // Create test users for each tenant (central database only)
        foreach ($tenants as $tenant) {
            $this->createTestUsers($tenant);
        }

        // Create super admin user
        $this->createSuperAdmin();
    }

    /**
     * Disable tenant database creation during seeding
     * This prevents PostgreSQL "CREATE DATABASE cannot run inside a transaction block" errors
     */
    private function disableTenantDatabaseCreation(): void
    {
        // Unbind the tenant creation jobs that try to create databases
        \Illuminate\Support\Facades\Event::forget(\Stancl\Tenancy\Events\TenantCreated::class);
        \Illuminate\Support\Facades\Event::forget(\Stancl\Tenancy\Events\TenantDeleted::class);
        
        // Instead, we'll use the central database for tenant data during tests
        // All tenant models will work with the central test database
    }

    /**
     * Create roles
     */
    private function createRoles(): void
    {
        $roles = [
            'super_admin',
            'campus_manager',
            'rector',
            'warden',
            'guard',
            'student',
        ];

        foreach ($roles as $role) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role]);
        }
    }

    /**
     * Create test users for a tenant
     */
    private function createTestUsers(Tenant $tenant): void
    {
        $phoneSuffix = substr($tenant->id, -4); // Use last 4 chars of tenant ID for uniqueness
        
        // Create campus manager
        $campusManager = User::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => "+919876543{$phoneSuffix}0"],
            [
                'name' => 'Campus Manager',
                'email' => "campus.manager@{$tenant->code}.edu",
                'kind' => 'campus_manager',
            ]
        );
        $campusManager->assignRole('campus_manager');

        // Create rector
        $rector = User::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => "+919876543{$phoneSuffix}1"],
            [
                'name' => 'Rector',
                'email' => "rector@{$tenant->code}.edu",
                'kind' => 'rector',
            ]
        );
        $rector->assignRole('rector');

        // Create warden
        $warden = User::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => "+919876543{$phoneSuffix}2"],
            [
                'name' => 'Warden',
                'email' => "warden@{$tenant->code}.edu",
                'kind' => 'warden',
            ]
        );
        $warden->assignRole('warden');

        // Create guard
        $guard = User::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => "+919876543{$phoneSuffix}3"],
            [
                'name' => 'Guard',
                'email' => "guard@{$tenant->code}.edu",
                'kind' => 'guard',
            ]
        );
        $guard->assignRole('guard');

        // Create student
        $student = User::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => "+919876543{$phoneSuffix}4"],
            [
                'name' => 'Test Student',
                'email' => "student@{$tenant->code}.edu",
                'kind' => 'student',
            ]
        );
        $student->assignRole('student');
    }

    /**
     * Create super admin user
     */
    private function createSuperAdmin(): void
    {
        $superAdmin = User::firstOrCreate(
            ['phone' => '+919876543200'],
            [
                'name' => 'Super Admin',
                'email' => 'admin@map-hms.dev',
                'kind' => 'super_admin',
            ]
        );
        $superAdmin->assignRole('super_admin');
    }
}
