<?php

namespace Tests\Seeds;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class TestBootstrapSeeder extends Seeder
{
    /**
     * Run the database seeds for test environment.
     * This ensures tests have the minimal required data without heavy demo seeding.
     */
    public function run(): void
    {
        // Seed roles and permissions (required for auth)
        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        
        // Seed baseline test data (tenant, campus, hostel, staff, students)
        Artisan::call('db:seed', ['--class' => 'TestingBaselineSeeder']);
    }
}
