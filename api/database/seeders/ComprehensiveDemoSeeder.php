<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ComprehensiveDemoSeeder extends Seeder
{
    /**
     * Master seeder for comprehensive demo data.
     * 
     * Orchestrates all demo seeders in the correct order to populate
     * the MAP HMS system with realistic sample data.
     * 
     * Usage:
     *   php artisan db:seed --class=ComprehensiveDemoSeeder
     * 
     * Or run individual phases:
     *   php artisan db:seed --class=DemoTenantsSeeder
     */
    public function run(): void
    {
        $this->command->info("🚀 Starting Comprehensive Demo Data Seeding...\n");
        $this->command->info("=" . str_repeat("=", 60) . "\n");

        // Phase 1: Core Infrastructure
        $this->command->info("📦 PHASE 1: Core Infrastructure");
        $this->command->info("-" . str_repeat("-", 60));
        
        $this->call([
            DemoTenantsSeeder::class,
            DemoCampusesSeeder::class,
            DemoHostelsSeeder::class,
            DemoRoomsSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info("=" . str_repeat("=", 60));
        $this->command->info("✅ Phase 1 Complete: Core Infrastructure\n");

        // Phase 2: Users & Roles
        $this->command->info("👥 PHASE 2: Users & Roles");
        $this->command->info("-" . str_repeat("-", 60));
        
        $this->call([
            DemoStaffUsersSeeder::class,
            DemoStudentsSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info("=" . str_repeat("=", 60));
        $this->command->info("✅ Phase 2 Complete: Users & Roles\n");

        // Phase 3: Operational Data
        $this->command->info("📝 PHASE 3: Operational Data");
        $this->command->info("-" . str_repeat("-", 60));
        
        $this->call([
            DemoNoticesSeeder::class,
            DemoTicketsSeeder::class,
            DemoIncidentsSeeder::class,
            DemoGateEntriesSeeder::class,
        ]);

        // Run checklist seeders only if the required tables exist
        try {
            if (\Schema::hasTable('checklist_templates')) {
                $this->call([DemoChecklistsSeeder::class]);
            } else {
                $this->command->warn('⚠️  Skipping DemoChecklistsSeeder (missing checklist tables)');
            }
        } catch (\Throwable $e) {
            $this->command->warn('⚠️  Skipping DemoChecklistsSeeder: ' . $e->getMessage());
        }

        $this->command->newLine();
        $this->command->info("=" . str_repeat("=", 60));
        $this->command->info("✅ Phase 3 Complete: Operational Data\n");

        // Phase 4: Add-on Modules (to be implemented)
        if (class_exists(DemoSportsFacilitiesSeeder::class)) {
            $this->command->info("🏅 PHASE 4: Add-on Modules");
            $this->command->info("-" . str_repeat("-", 60));
            
            $this->call([
                DemoSportsFacilitiesSeeder::class,
                DemoSportsEventsSeeder::class,
                DemoLaundryRequestsSeeder::class,
                DemoChecklistTemplatesSeeder::class,
                DemoChecklistInstancesSeeder::class,
                DemoAttendanceSessionsSeeder::class,
            ]);

            $this->command->newLine();
            $this->command->info("=" . str_repeat("=", 60));
            $this->command->info("✅ Phase 4 Complete: Add-on Modules\n");
        } else {
            $this->command->warn("⚠️  Phase 4 seeders not yet implemented");
        }

        // Summary
        $this->command->info("=" . str_repeat("=", 60));
        $this->command->info("🎉 COMPREHENSIVE DEMO SEEDING COMPLETE!");
        $this->command->info("=" . str_repeat("=", 60) . "\n");

        $this->displaySummary();
    }

    /**
     * Display seeding summary with counts
     */
    private function displaySummary(): void
    {
        $this->command->info("📊 Summary:");
        $this->command->info("-" . str_repeat("-", 60));

        $stats = [
            'Tenants' => \App\Models\Tenant::count(),
            'Campuses' => \App\Models\Campus::count(),
            'Hostels' => \App\Models\Hostel::count(),
            'Rooms' => \App\Models\Room::count(),
        ];

        // Add user counts if seeders exist
        if (class_exists(\App\Models\User::class)) {
            $stats['Total Users'] = \App\Models\User::count();
            $stats['Students'] = \App\Models\User::where('kind', 'student')->count();
            $stats['Staff'] = \App\Models\User::where('kind', 'staff')->count();
        }

        foreach ($stats as $label => $count) {
            $this->command->info(sprintf("   %-20s: %d", $label, $count));
        }

        $this->command->newLine();
        $this->command->info("✅ Demo data ready for testing!");
        $this->command->info("   Login as Super Admin: admin@mapservices.in / Admin@123");
        $this->command->newLine();
    }
}

