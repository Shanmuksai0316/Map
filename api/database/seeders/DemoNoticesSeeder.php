<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Notice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoNoticesSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::whereIn('code', ['STXAV', 'NITKT', 'CHRUN', 'ANUN', 'DLART'])->get();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📢 Creating notices for {$tenant->name}...");
            
            // Run in tenant context
            $tenant->run(function () use ($tenant, &$totalCreated) {
                $campuses = Campus::all();
                $hostels = Hostel::all();
                
                if ($campuses->isEmpty()) {
                    $this->command->warn("  ⚠️  No campuses found for {$tenant->name}, skipping...");
                    return;
                }

                // Get a staff user as creator
                $creator = User::on('pgsql')
                    ->where('tenant_id', $tenant->id)
                    ->where('kind', 'staff')
                    ->first();
                
                if (!$creator) {
                    $this->command->warn("  ⚠️  No staff found for {$tenant->name}, skipping...");
                    return;
                }

                // Campus-wide notices (assign to first hostel)
                $firstHostel = $hostels->first();
                if ($firstHostel) {
                    $campusNotices = [
                        [
                            'title' => 'Campus Maintenance Schedule',
                            'content' => "Dear Students,\n\nPlease be informed that routine maintenance work will be carried out in the campus premises on Sunday from 8:00 AM to 2:00 PM. We request your cooperation.",
                            'priority' => 'high',
                            'is_published' => true,
                        ],
                        [
                            'title' => 'Library Extended Hours',
                            'content' => "The central library will remain open until 10:00 PM during the examination period starting next week. Make the most of this facility!",
                            'priority' => 'normal',
                            'is_published' => true,
                        ],
                        [
                            'title' => 'Sports Day Announcement',
                            'content' => "Our annual Sports Day is scheduled for next month. All interested students are requested to register at the sports office.",
                            'priority' => 'low',
                            'is_published' => false,
                        ],
                    ];

                    foreach ($campusNotices as $data) {
                        Notice::create([
                            'hostel_id' => null, // Campus-wide, no specific hostel
                            'title' => $data['title'],
                            'content' => $data['content'],
                            'priority' => $data['priority'],
                            'is_published' => $data['is_published'],
                            'published_at' => $data['is_published'] ? now()->subDays(rand(1, 10)) : null,
                            'created_by' => $creator->id,
                        ]);
                        $totalCreated++;
                    }
                }

                // Hostel-specific notices
                foreach ($hostels as $hostel) {
                    $hostelNotices = [
                        [
                            'title' => 'Hostel Wi-Fi Maintenance',
                            'content' => "Wi-Fi services in {$hostel->name} will be temporarily unavailable tomorrow from 2:00 PM to 4:00 PM due to router upgrades.",
                            'priority' => 'high',
                            'is_published' => true,
                        ],
                        [
                            'title' => 'Room Inspection Schedule',
                            'content' => "Routine room inspections will be conducted this Friday. Please ensure your rooms are clean and tidy.",
                            'priority' => 'normal',
                            'is_published' => true,
                        ],
                        [
                            'title' => 'Mess Menu Update',
                            'content' => "New special menu items have been added starting this week. Check the mess notice board for details.",
                            'priority' => 'low',
                            'is_published' => true,
                        ],
                        [
                            'title' => 'Visitor Timing Changes',
                            'content' => "Due to upcoming examinations, visitor timings will be restricted to 4:00 PM - 6:00 PM only.",
                            'priority' => 'urgent',
                            'is_published' => false,
                        ],
                    ];

                    foreach ($hostelNotices as $data) {
                        Notice::create([
                            'hostel_id' => $hostel->id,
                            'title' => $data['title'],
                            'content' => $data['content'],
                            'priority' => $data['priority'],
                            'is_published' => $data['is_published'],
                            'published_at' => $data['is_published'] ? now()->subDays(rand(1, 5)) : null,
                            'created_by' => $creator->id,
                        ]);
                        $totalCreated++;
                    }
                }

                $this->command->info("  ✅ Created notices for {$tenant->name}");
            });
        }

        $this->command->info("\n✅ Demo notices seeding complete!");
        $this->command->info("Total notices created: {$totalCreated}");
    }
}

