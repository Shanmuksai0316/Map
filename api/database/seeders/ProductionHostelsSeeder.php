<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Production Hostels Seeder
 * 
 * Creates 4-6 hostels per tenant with Indian names.
 * Boys/Girls hostels with realistic Indian naming conventions.
 */
class ProductionHostelsSeeder extends Seeder
{
    /**
     * Indian hostel names
     */
    private array $boysHostelNames = [
        'Aryabhatta Hall',
        'Ramanujan Hostel',
        'Tagore House',
        'Vivekananda Hall',
        'Gandhi Hostel',
        'Nehru House',
    ];

    private array $girlsHostelNames = [
        'Sarojini Hostel',
        'Kalpana Chawla Hall',
        'Indira House',
        'Kasturba Hostel',
        'Mother Teresa Hall',
        'Lakshmi Bai Hostel',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏠 Creating hostels for each campus...');

        $tenants = Tenant::all();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $campuses = Campus::where('tenant_id', $tenant->id)->get();

            foreach ($campuses as $campus) {
                $hostelConfigs = $this->getHostelConfigsForCampus($campus, $tenant);

                foreach ($hostelConfigs as $config) {
                    $existing = Hostel::where('tenant_id', $tenant->id)
                        ->where('campus_id', $campus->id)
                        ->where('code', $config['code'])
                        ->first();

                    if (!$existing) {
                        $hostel = Hostel::create([
                            'tenant_id' => $tenant->id,
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
        }

        $this->command->info("\n✅ Production hostels seeding complete!");
        $this->command->info("Total hostels created: {$totalCreated}");
    }

    /**
     * Get hostel configurations for a campus
     */
    private function getHostelConfigsForCampus(Campus $campus, Tenant $tenant): array
    {
        $prefix = $campus->code;
        $configs = [];
        $boysIndex = 0;
        $girlsIndex = 0;

        // Main campus gets more hostels
        $hostelCount = $campus->code === 'MAIN' ? 4 : 2;

        // Boys hostels
        for ($i = 0; $i < ($hostelCount / 2); $i++) {
            $configs[] = [
                'code' => "{$prefix}-BH" . ($i + 1),
                'name' => $this->boysHostelNames[$boysIndex % count($this->boysHostelNames)],
                'gender_mode' => 'male',
                'capacity' => rand(100, 150),
                'phone' => '+919' . rand(100000000, 999999999),
                'curfew_time' => '23:00:00',
                'overnight_enabled' => true,
            ];
            $boysIndex++;
        }

        // Girls hostels
        for ($i = 0; $i < ($hostelCount / 2); $i++) {
            $configs[] = [
                'code' => "{$prefix}-GH" . ($i + 1),
                'name' => $this->girlsHostelNames[$girlsIndex % count($this->girlsHostelNames)],
                'gender_mode' => 'female',
                'capacity' => rand(80, 120),
                'phone' => '+919' . rand(100000000, 999999999),
                'curfew_time' => '22:00:00',
                'overnight_enabled' => true,
            ];
            $girlsIndex++;
        }

        // Add PG/Coed hostel for larger institutions
        if (in_array($tenant->code, ['IITD', 'NITK', 'JNU']) && $campus->code === 'MAIN') {
            $configs[] = [
                'code' => "{$prefix}-PG",
                'name' => 'Post Graduate Block',
                'gender_mode' => 'coed',
                'capacity' => 50,
                'phone' => '+919' . rand(100000000, 999999999),
                'curfew_time' => '23:30:00',
                'overnight_enabled' => false,
            ];
        }

        return $configs;
    }
}

