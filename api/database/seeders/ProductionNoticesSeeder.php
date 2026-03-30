<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Notice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production Notices Seeder
 * 
 * Creates 20-30 notices per tenant (campus-wide and hostel-specific).
 */
class ProductionNoticesSeeder extends Seeder
{
    private array $campusNotices = [
        ['title' => 'Campus Maintenance Schedule', 'content' => "Dear Students,\n\nPlease be informed that routine maintenance work will be carried out in the campus premises on Sunday from 8:00 AM to 2:00 PM. We request your cooperation.", 'priority' => 'high'],
        ['title' => 'Library Extended Hours', 'content' => "The central library will remain open until 10:00 PM during the examination period starting next week. Make the most of this facility!", 'priority' => 'normal'],
        ['title' => 'Sports Day Announcement', 'content' => "Our annual Sports Day is scheduled for next month. All interested students are requested to register at the sports office.", 'priority' => 'low'],
        ['title' => 'Diwali Holidays', 'content' => "The college will remain closed from 12th to 15th November for Diwali holidays. Hostel mess will remain operational.", 'priority' => 'normal'],
        ['title' => 'Examination Schedule', 'content' => "Mid-semester examinations will commence from next week. Please check the notice board for detailed schedule.", 'priority' => 'high'],
    ];

    private array $hostelNotices = [
        ['title' => 'Hostel Wi-Fi Maintenance', 'content' => "Wi-Fi services in the hostel will be temporarily unavailable tomorrow from 2:00 PM to 4:00 PM due to router upgrades.", 'priority' => 'high'],
        ['title' => 'Room Inspection Schedule', 'content' => "Routine room inspections will be conducted this Friday. Please ensure your rooms are clean and tidy.", 'priority' => 'normal'],
        ['title' => 'Mess Menu Update', 'content' => "New special menu items have been added starting this week. Check the mess notice board for details.", 'priority' => 'low'],
        ['title' => 'Visitor Timing Changes', 'content' => "Due to upcoming examinations, visitor timings will be restricted to 4:00 PM - 6:00 PM only.", 'priority' => 'urgent'],
        ['title' => 'Water Supply Disruption', 'content' => "Water supply will be disrupted on Sunday from 8:00 AM to 12:00 PM for maintenance work. Please store water in advance.", 'priority' => 'high'],
        ['title' => 'Laundry Service Update', 'content' => "Laundry service will be available on Monday, Wednesday, and Friday this week due to staff shortage.", 'priority' => 'normal'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📢 Creating notices for each tenant...');

        $tenants = Tenant::all();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📋 Creating notices for {$tenant->name}...");
            
            $campuses = Campus::where('tenant_id', $tenant->id)->get();
            $hostels = Hostel::where('tenant_id', $tenant->id)->get();
            
            if ($campuses->isEmpty()) {
                $this->command->warn("  ⚠️  No campuses found for {$tenant->name}, skipping...");
                continue;
            }

            // Get staff user as creator
            $creator = User::where('tenant_id', $tenant->id)
                ->where('kind', 'staff')
                ->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['Campus Manager', 'Warden']);
                })
                ->first();

            if (!$creator) {
                $this->command->warn("  ⚠️  No staff found for {$tenant->name}, skipping...");
                continue;
            }

            // Campus-wide notices
            $campus = $campuses->first();
            foreach ($this->campusNotices as $noticeData) {
                Notice::create([
                    'tenant_id' => $tenant->id,
                    'campus_id' => $campus->id,
                    'hostel_id' => null, // Campus-wide
                    'title' => $noticeData['title'],
                    'body' => $noticeData['content'],
                    'priority' => $noticeData['priority'],
                    'is_published' => true,
                    'published_at' => now()->subDays(rand(1, 10)),
                    'created_by' => $creator->id,
                ]);
                $totalCreated++;
            }

            // Hostel-specific notices
            foreach ($hostels as $hostel) {
                foreach ($this->hostelNotices as $noticeData) {
                    Notice::create([
                        'tenant_id' => $tenant->id,
                        'campus_id' => $hostel->campus_id,
                        'hostel_id' => $hostel->id,
                        'title' => $noticeData['title'],
                        'body' => str_replace('the hostel', $hostel->name, $noticeData['content']),
                        'priority' => $noticeData['priority'],
                        'is_published' => true,
                        'published_at' => now()->subDays(rand(1, 5)),
                        'created_by' => $creator->id,
                    ]);
                    $totalCreated++;
                }
            }

            $this->command->info("  ✅ Created notices for {$tenant->name}");
        }

        $this->command->info("\n✅ Production notices seeding complete!");
        $this->command->info("Total notices created: {$totalCreated}");
    }
}

