<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Production Tenants Seeder - Indian Colleges/Universities
 * 
 * Creates 4 Indian colleges/universities with realistic names and addresses.
 */
class ProductionTenantsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏛️  Creating Indian colleges/universities...');

        $tenants = [
            [
                'code' => 'MAP-IITD',
                'name' => 'Indian Institute of Technology Delhi',
                'subdomain' => 'iitd',
                'status' => 'active',
                'settings' => [
                    'contact' => [
                        'name' => 'Dr. Ramesh Kumar',
                        'phone' => '+919876543210',
                        'email' => 'admin@iitd.ac.in',
                    ],
                    'address' => [
                        'street' => 'Hauz Khas',
                        'city' => 'New Delhi',
                        'state' => 'Delhi',
                        'pincode' => '110016',
                        'country' => 'India',
                    ],
                    'branding' => [
                        'primary_color' => '#1e40af',
                        'secondary_color' => '#f59e0b',
                    ],
                ],
                'addon_security' => true,
                'addon_sports' => true,
                'addon_laundry' => true,
            ],
            [
                'code' => 'MAP-NITK',
                'name' => 'National Institute of Technology Karnataka',
                'subdomain' => 'nitk',
                'status' => 'active',
                'settings' => [
                    'contact' => [
                        'name' => 'Dr. Suresh Reddy',
                        'phone' => '+919876543211',
                        'email' => 'admin@nitk.ac.in',
                    ],
                    'address' => [
                        'street' => 'Surathkal',
                        'city' => 'Mangalore',
                        'state' => 'Karnataka',
                        'pincode' => '575025',
                        'country' => 'India',
                    ],
                    'branding' => [
                        'primary_color' => '#dc2626',
                        'secondary_color' => '#fbbf24',
                    ],
                ],
                'addon_security' => true,
                'addon_sports' => true,
                'addon_laundry' => true,
            ],
            [
                'code' => 'MAP-JNU',
                'name' => 'Jawaharlal Nehru University',
                'subdomain' => 'jnu',
                'status' => 'active',
                'settings' => [
                    'contact' => [
                        'name' => 'Dr. Priya Sharma',
                        'phone' => '+919876543212',
                        'email' => 'admin@jnu.ac.in',
                    ],
                    'address' => [
                        'street' => 'New Mehrauli Road',
                        'city' => 'New Delhi',
                        'state' => 'Delhi',
                        'pincode' => '110067',
                        'country' => 'India',
                    ],
                    'branding' => [
                        'primary_color' => '#059669',
                        'secondary_color' => '#3b82f6',
                    ],
                ],
                'addon_security' => true,
                'addon_sports' => true,
                'addon_laundry' => false,
            ],
            [
                'code' => 'MAP-DU',
                'name' => 'University of Delhi',
                'subdomain' => 'du',
                'status' => 'active',
                'settings' => [
                    'contact' => [
                        'name' => 'Dr. Amit Verma',
                        'phone' => '+919876543213',
                        'email' => 'admin@du.ac.in',
                    ],
                    'address' => [
                        'street' => 'North Campus',
                        'city' => 'Delhi',
                        'state' => 'Delhi',
                        'pincode' => '110007',
                        'country' => 'India',
                    ],
                    'branding' => [
                        'primary_color' => '#7c3aed',
                        'secondary_color' => '#ec4899',
                    ],
                ],
                'addon_security' => true,
                'addon_sports' => false,
                'addon_laundry' => true,
            ],
        ];

        foreach ($tenants as $tenantData) {
            $existing = Tenant::where('code', $tenantData['code'])->first();
            
            if (!$existing) {
                $tenant = Tenant::create($tenantData);
                $this->command->info("✅ Created tenant: {$tenant->name} ({$tenant->code})");
                
                // Create domain
                $subdomain = $tenantData['subdomain'] ?? strtolower(str_replace('MAP-', '', $tenantData['code']));
                $domainName = "{$subdomain}.mapservices.in";
                try {
                    if (!$tenant->domains()->where('domain', $domainName)->exists()) {
                        $tenant->domains()->create(['domain' => $domainName]);
                        $this->command->info("   → Domain: {$domainName}");
                    } else {
                        $this->command->info("   → Domain already exists: {$domainName}");
                    }
                } catch (\Exception $e) {
                    $this->command->warn("   ⚠️  Could not create domain: {$e->getMessage()}");
                }
            } else {
                $this->command->warn("⚠️  Tenant {$tenantData['code']} already exists, skipping...");
            }
        }

        $this->command->info("\n✅ Production tenants seeding complete!");
        $this->command->info("Total tenants: " . Tenant::count());
    }
}

