<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production India Seeder - Master Orchestrator
 * 
 * Creates comprehensive Indian-specific dummy data for production testing.
 * Covers all features: OutPass, Attendance, Tickets, Notices, Visitors, Gate Events,
 * Laundry, Sports, Checklists, Payments, Incidents.
 * 
 * Usage:
 *   php artisan db:seed --class=ProductionIndiaSeeder
 * 
 * This will create:
 * - 4 Indian colleges/universities
 * - Multiple campuses and hostels per college
 * - 200-300 students per tenant with Indian names/addresses
 * - All 10 staff roles with Indian names
 * - Comprehensive data for all features
 */
class ProductionIndiaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info("🇮🇳 Starting Production India Data Seeding...");
        $this->command->info("=" . str_repeat("=", 70) . "\n");

        // Phase 1: Core Infrastructure
        $this->command->info("📦 PHASE 1: Core Infrastructure");
        $this->command->info("-" . str_repeat("-", 70));
        
        $this->call([
            ProductionTenantsSeeder::class,
            ProductionCampusesSeeder::class,
            ProductionHostelsSeeder::class,
            ProductionRoomsSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info("=" . str_repeat("=", 70));
        $this->command->info("✅ Phase 1 Complete: Core Infrastructure\n");

        // Phase 2: Users & Roles
        $this->command->info("👥 PHASE 2: Users & Roles");
        $this->command->info("-" . str_repeat("-", 70));
        
        $this->call([
            ProductionStaffSeeder::class,
            ProductionStudentsSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info("=" . str_repeat("=", 70));
        $this->command->info("✅ Phase 2 Complete: Users & Roles\n");

        // Phase 3: Operational Data
        $this->command->info("📝 PHASE 3: Operational Data");
        $this->command->info("-" . str_repeat("-", 70));
        
        $this->call([
            ProductionOutPassesSeeder::class,
            ProductionAttendanceSeeder::class,
            ProductionTicketsSeeder::class,
            ProductionNoticesSeeder::class,
            ProductionVisitorsSeeder::class,
            ProductionGateEventsSeeder::class,
            ProductionIncidentsSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info("=" . str_repeat("=", 70));
        $this->command->info("✅ Phase 3 Complete: Operational Data\n");

        // Phase 4: Add-on Modules
        $this->command->info("🎯 PHASE 4: Add-on Modules");
        $this->command->info("-" . str_repeat("-", 70));
        
        $this->call([
            ProductionLaundrySeeder::class,
            ProductionSportsSeeder::class,
            ProductionChecklistsSeeder::class,
            ProductionPaymentsSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info("=" . str_repeat("=", 70));
        $this->command->info("✅ Phase 4 Complete: Add-on Modules\n");

        // Summary
        $this->command->info("=" . str_repeat("=", 70));
        $this->command->info("🎉 PRODUCTION INDIA SEEDING COMPLETE!");
        $this->command->info("=" . str_repeat("=", 70) . "\n");

        $this->displaySummary();
    }

    /**
     * Display seeding summary with counts
     */
    private function displaySummary(): void
    {
        $this->command->info("📊 Summary:");
        $this->command->info("-" . str_repeat("-", 70));

        $stats = [
            'Tenants' => \App\Models\Tenant::count(),
            'Campuses' => \App\Models\Campus::count(),
            'Hostels' => \App\Models\Hostel::count(),
            'Rooms' => \App\Models\Room::count(),
            'Total Users' => \App\Models\User::count(),
            'Students' => \App\Models\User::where('kind', 'student')->count(),
            'Staff' => \App\Models\User::where('kind', 'staff')->count(),
            'OutPasses' => \App\Models\Domain\OutPass\OutPass::count(),
            'Tickets' => \App\Domain\Tickets\Models\Ticket::count(),
            'Notices' => \App\Models\Notice::count(),
            'Attendance Sessions' => \App\Models\AttendanceSession::count(),
            'Laundry Jobs' => \App\Models\LaundryRequest::count(),
            'Sports Events' => \App\Models\SportsEvent::count(),
            'Payment Requests' => \App\Models\PaymentRequest::count() ?? 0,
        ];

        foreach ($stats as $label => $count) {
            $this->command->info(sprintf("   %-25s: %d", $label, $count));
        }

        $this->command->newLine();
        $this->command->info("✅ Production data ready for testing!");
        $this->command->info("📄 Credentials saved to: docs/demo/ProductionCredentials.md");
        $this->command->newLine();
    }
}

