<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DemoCampusesSeeder extends Seeder
{
    /**
     * Seed demo campuses for each tenant.
     * Creates 1-2 campuses per tenant with realistic data.
     */
    public function run(): void
    {
        $campusConfigs = [
            'STXAV' => [
                ['name' => 'Main Campus', 'code' => 'MC', 'is_primary' => true],
                ['name' => 'Annexe Campus', 'code' => 'AC', 'is_primary' => false],
            ],
            'NITKT' => [
                ['name' => 'Central Campus', 'code' => 'CC', 'is_primary' => true],
            ],
            'CHRUN' => [
                ['name' => 'Bangalore Campus', 'code' => 'BC', 'is_primary' => true],
                ['name' => 'Satellite Campus', 'code' => 'SC', 'is_primary' => false],
            ],
            'ANUN' => [
                ['name' => 'Chennai Regional Campus', 'code' => 'CRC', 'is_primary' => true],
            ],
            'DLART' => [
                ['name' => 'North Campus', 'code' => 'NC', 'is_primary' => true],
            ],
        ];

        $tenants = Tenant::whereIn('code', array_keys($campusConfigs))->get();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $configs = $campusConfigs[$tenant->code] ?? [];
            
            // Switch to tenant database context
            $tenant->run(function () use ($tenant, $configs, &$totalCreated) {
                foreach ($configs as $config) {
                    $existing = Campus::where('code', $config['code'])->first();

                    if (!$existing) {
                        $campus = Campus::create([
                            'code' => $config['code'],
                            'name' => $config['name'],
                            'address' => [
                                'street' => $tenant->settings['address']['street'] ?? '',
                                'city' => $tenant->settings['address']['city'] ?? '',
                                'state' => $tenant->settings['address']['state'] ?? '',
                                'pincode' => $tenant->settings['address']['pincode'] ?? '',
                                'country' => $tenant->settings['address']['country'] ?? 'India',
                            ],
                        ]);

                        $this->command->info("✅ Created campus: {$campus->name} for {$tenant->name}");
                        $totalCreated++;
                    } else {
                        $this->command->warn("⚠️  Campus {$config['code']} for {$tenant->code} already exists, skipping...");
                    }
                }
            });
        }

        $this->command->info("\n✅ Demo campuses seeding complete!");
        $this->command->info("Total campuses created: {$totalCreated}");
    }
}

