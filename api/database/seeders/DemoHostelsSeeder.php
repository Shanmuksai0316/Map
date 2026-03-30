<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DemoHostelsSeeder extends Seeder
{
    /**
     * Seed demo hostels for each campus.
     * Creates 2-4 hostels per campus with varied configurations.
     */
    public function run(): void
    {
        $tenants = Tenant::all();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            // Switch to tenant database context
            $tenant->run(function() use ($tenant, &$totalCreated) {
                $campuses = Campus::all();

                foreach ($campuses as $campus) {
                    $hostelConfigs = $this->getHostelConfigsForCampus($campus, $tenant);

                    foreach ($hostelConfigs as $config) {
                        $existing = Hostel::where('campus_id', $campus->id)
                            ->where('code', $config['code'])
                            ->first();

                        if (!$existing) {
                            $hostel = Hostel::create([
                                'campus_id' => $campus->id,
                                'code' => $config['code'],
                                'name' => $config['name'],
                                'gender_mode' => $config['gender_mode'],
                                'overnight_enabled' => $config['overnight_enabled'] ?? true,
                                'curfew_time' => $config['curfew_time'] ?? '22:00:00',
                                'visiting_start' => '09:00:00',
                                'visiting_end' => '18:00:00',
                                'settings' => [
                                    'capacity' => $config['capacity'],
                                    'phone' => $config['phone'] ?? '',
                                ],
                            ]);

                            $this->command->info("✅ Created hostel: {$hostel->name} ({$hostel->gender_mode}) - Capacity: {$config['capacity']}");
                            $totalCreated++;
                        } else {
                            $this->command->warn("⚠️  Hostel {$config['code']} already exists, skipping...");
                        }
                    }
                }
            });
        }

        $this->command->info("\n✅ Demo hostels seeding complete!");
        $this->command->info("Total hostels created: {$totalCreated}");
    }

    /**
     * Get hostel configurations for a campus based on tenant and campus size
     */
    private function getHostelConfigsForCampus(Campus $campus, Tenant $tenant): array
    {
        // Make codes unique per campus by using campus code prefix
        $prefix = $campus->code;
        
        // Base configuration for most campuses
        $configs = [
            [
                'code' => "{$prefix}-BHA",
                'name' => 'Boys Hostel A',
                'gender_mode' => 'male',
                'capacity' => 100,
                'phone' => '+919876500001',
                'curfew_time' => '23:00:00',
                'overnight_enabled' => true,
            ],
            [
                'code' => "{$prefix}-GHB",
                'name' => 'Girls Hostel B',
                'gender_mode' => 'female',
                'capacity' => 80,
                'phone' => '+919876500002',
                'curfew_time' => '22:00:00',
                'overnight_enabled' => true,
            ],
        ];

        // Add additional hostels for larger institutions
        if (in_array($tenant->code, ['STXAV', 'CHRUN', 'NITKT'])) {
            $configs[] = [
                'code' => "{$prefix}-PGC",
                'name' => 'PG Block',
                'gender_mode' => 'coed',
                'capacity' => 50,
                'phone' => '+919876500003',
                'curfew_time' => '23:30:00',
                'overnight_enabled' => false,
            ];
        }

        // Add international hostel for premier institutions
        if (in_array($tenant->code, ['CHRUN', 'NITKT'])) {
            $configs[] = [
                'code' => "{$prefix}-IHD",
                'name' => 'International House',
                'gender_mode' => 'coed',
                'capacity' => 40,
                'phone' => '+919876500004',
                'curfew_time' => '00:00:00',
                'overnight_enabled' => false,
            ];
        }

        return $configs;
    }
}

