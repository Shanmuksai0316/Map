<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class TestAccountsSeeder extends Seeder
{
    /**
     * Create test accounts for Super Admin, Rector, and Campus Manager roles
     * These accounts are designed for testing and development purposes
     */
    public function run(): void
    {
        $this->command->info('Creating test accounts for Super Admin, Rector, and Campus Manager...');

        // Ensure roles exist
        $this->ensureRolesExist();

        // Get or create MAP-ROOT tenant
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

        // Create Super Admin test account
        $superAdmin = User::updateOrCreate(
            ['phone' => '+910000000000'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Super Admin Test',
                'email' => 'superadmin@map-test.com',
                'kind' => 'SuperAdmin',
                'password' => Hash::make('password123'), // For API login if needed
            ]
        );
        
        if (!$superAdmin->hasRole('Super Admin')) {
            $superAdmin->assignRole('Super Admin');
        }

        // Create Rector test account
        $rector = User::updateOrCreate(
            ['phone' => '+910000000001'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Rector Test',
                'email' => 'rector@map-test.com',
                'kind' => 'Rector',
                'password' => Hash::make('password123'), // For API login if needed
            ]
        );
        
        if (!$rector->hasRole('Rector')) {
            $rector->assignRole('Rector');
        }

        // Create Campus Manager test account
        $campusManager = User::updateOrCreate(
            ['phone' => '+910000000002'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Campus Manager Test',
                'email' => 'campusmanager@map-test.com',
                'kind' => 'CampusManager',
                'password' => Hash::make('password123'), // For API login if needed
            ]
        );
        
        if (!$campusManager->hasRole('Campus Manager')) {
            $campusManager->assignRole('Campus Manager');
        }

        // Create additional test tenant for multi-tenant testing
        $testTenant = Tenant::firstOrCreate([
            'code' => 'TEST-COLLEGE',
        ], [
            'name' => 'Test College',
            'addon_security' => true,
            'addon_sports' => false,
            'addon_laundry' => true,
            'settings' => [],
        ]);

        // Ensure domain exists for test tenant
        if (!$testTenant->domains()->exists()) {
            $testTenant->domains()->create([
                'domain' => 'test-college.localhost',
            ]);
        }

        // Create Rector for test tenant
        $testRector = User::updateOrCreate(
            ['phone' => '+910000000003'],
            [
                'tenant_id' => $testTenant->id,
                'name' => 'Test College Rector',
                'email' => 'rector@test-college.com',
                'kind' => 'Rector',
                'password' => Hash::make('password123'),
            ]
        );
        
        if (!$testRector->hasRole('Rector')) {
            $testRector->assignRole('Rector');
        }

        // Create Campus Manager for test tenant
        $testCampusManager = User::updateOrCreate(
            ['phone' => '+910000000004'],
            [
                'tenant_id' => $testTenant->id,
                'name' => 'Test College Campus Manager',
                'email' => 'campusmanager@test-college.com',
                'kind' => 'CampusManager',
                'password' => Hash::make('password123'),
            ]
        );
        
        if (!$testCampusManager->hasRole('Campus Manager')) {
            $testCampusManager->assignRole('Campus Manager');
        }

        $this->command->info('Test accounts created successfully!');
        $this->command->info('');
        $this->command->info('=== TEST ACCOUNTS CREATED ===');
        $this->command->info('');
        $this->command->info('SUPER ADMIN (MAP-ROOT Tenant):');
        $this->command->info('  Phone: +910000000000');
        $this->command->info('  Email: superadmin@map-test.com');
        $this->command->info('  Password: password123');
        $this->command->info('');
        $this->command->info('RECTOR (MAP-ROOT Tenant):');
        $this->command->info('  Phone: +910000000001');
        $this->command->info('  Email: rector@map-test.com');
        $this->command->info('  Password: password123');
        $this->command->info('');
        $this->command->info('CAMPUS MANAGER (MAP-ROOT Tenant):');
        $this->command->info('  Phone: +910000000002');
        $this->command->info('  Email: campusmanager@map-test.com');
        $this->command->info('  Password: password123');
        $this->command->info('');
        $this->command->info('RECTOR (TEST-COLLEGE Tenant):');
        $this->command->info('  Phone: +910000000003');
        $this->command->info('  Email: rector@test-college.com');
        $this->command->info('  Password: password123');
        $this->command->info('');
        $this->command->info('CAMPUS MANAGER (TEST-COLLEGE Tenant):');
        $this->command->info('  Phone: +910000000004');
        $this->command->info('  Email: campusmanager@test-college.com');
        $this->command->info('  Password: password123');
        $this->command->info('');
        $this->command->info('=== LOGIN METHODS ===');
        $this->command->info('1. Mobile App: Use phone number + OTP');
        $this->command->info('2. API: Use email + password');
        $this->command->info('3. Web Panel: Use appropriate tenant domain');
        $this->command->info('');
        $this->command->info('=== OTP TESTING ===');
        $this->command->info('When using OTP login, check the Laravel logs for the OTP code.');
        $this->command->info('In local environment, OTP is also returned in API response.');
    }

    /**
     * Ensure all required roles exist
     */
    private function ensureRolesExist(): void
    {
        $roles = [
            'Super Admin',
            'Campus Manager', 
            'Rector',
            'Warden',
            'Guard',
            'Student',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        // Ensure Super Admin has all permissions
        $superAdminRole = Role::findByName('Super Admin');
        $allPermissions = Permission::all();
        $superAdminRole->syncPermissions($allPermissions);
    }
}



