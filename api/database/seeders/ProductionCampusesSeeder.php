<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Production Campuses Seeder
 * 
 * Creates 2-3 campuses per tenant with Indian addresses.
 */
class ProductionCampusesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏢 Creating campuses for each tenant...');

        $tenants = Tenant::all();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $campusConfigs = $this->getCampusConfigsForTenant($tenant);

            foreach ($campusConfigs as $config) {
                $existing = Campus::where('tenant_id', $tenant->id)
                    ->where('code', $config['code'])
                    ->first();

                if (!$existing) {
                    $campus = Campus::create([
                        'tenant_id' => $tenant->id,
                        'code' => $config['code'],
                        'name' => $config['name'],
                        'address' => $config['address'],
                    ]);

                    $this->command->info("✅ Created campus: {$campus->name} for {$tenant->name}");
                    $totalCreated++;
                } else {
                    $this->command->warn("⚠️  Campus {$config['code']} already exists for {$tenant->name}, skipping...");
                }
            }
        }

        $this->command->info("\n✅ Production campuses seeding complete!");
        $this->command->info("Total campuses created: {$totalCreated}");
    }

    /**
     * Get campus configurations for a tenant
     */
    private function getCampusConfigsForTenant(Tenant $tenant): array
    {
        $configs = [
            [
                'code' => 'MAIN',
                'name' => 'Main Campus',
                'address' => [
                    'city' => $tenant->settings['address']['city'] ?? 'Delhi',
                    'state' => $tenant->settings['address']['state'] ?? 'Delhi',
                    'pincode' => $tenant->settings['address']['pincode'] ?? '110001',
                ],
            ],
        ];

        // Add additional campuses for larger institutions
        if (in_array($tenant->code, ['IITD', 'JNU', 'DU'])) {
            $configs[] = [
                'code' => 'SOUTH',
                'name' => 'South Campus',
                'address' => [
                    'city' => $tenant->settings['address']['city'] ?? 'Delhi',
                    'state' => $tenant->settings['address']['state'] ?? 'Delhi',
                    'pincode' => $tenant->settings['address']['pincode'] ?? '110021',
                ],
            ];
        }

        // Add North Campus for DU
        if ($tenant->code === 'DU') {
            $configs[] = [
                'code' => 'NORTH',
                'name' => 'North Campus',
                'address' => [
                    'city' => 'Delhi',
                    'state' => 'Delhi',
                    'pincode' => '110007',
                ],
            ];
        }

        return $configs;
    }
}

