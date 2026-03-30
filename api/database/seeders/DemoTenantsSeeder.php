<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoTenantsSeeder extends Seeder
{
    /**
     * Seed demo tenants (institutions) with realistic data.
     * Creates 5 sample colleges with different configurations.
     */
    public function run(): void
    {
        $tenants = [
            [
                'code' => 'STXAV',
                'name' => "St. Xavier's College of Engineering",
                'subdomain' => 'stxaviers',
                'status' => 'active',
                'settings' => [
                    'contact' => [
                        'name' => 'Dr. John Smith',
                        'phone' => '+919876543210',
                        'email' => 'admin@stxaviers.edu',
                    ],
                    'address' => [
                        'street' => '5 Mahapalika Marg',
                        'city' => 'Mumbai',
                        'state' => 'Maharashtra',
                        'pincode' => '400001',
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
                'code' => 'NITKT',
                'name' => 'National Institute of Technology',
                'subdomain' => 'nitkota',
                'status' => 'active',
                'settings' => [
                    'contact' => [
                        'name' => 'Prof. Rajesh Kumar',
                        'phone' => '+919876543211',
                        'email' => 'admin@nitkota.ac.in',
                    ],
                    'address' => [
                        'street' => 'NIT Campus, Rawatbhata Road',
                        'city' => 'Kota',
                        'state' => 'Rajasthan',
                        'pincode' => '324005',
                        'country' => 'India',
                    ],
                    'branding' => [
                        'primary_color' => '#dc2626',
                        'secondary_color' => '#fbbf24',
                    ],
                ],
                'addon_security' => true,
                'addon_sports' => true,
                'addon_laundry' => false,
            ],
            [
                'code' => 'CHRUN',
                'name' => 'Christ University',
                'subdomain' => 'christuniv',
                'status' => 'active',
                'settings' => [
                    'contact' => [
                        'name' => 'Dr. Mary Joseph',
                        'phone' => '+919876543212',
                        'email' => 'admin@christuniversity.in',
                    ],
                    'address' => [
                        'street' => 'Hosur Road, Bhavani Nagar',
                        'city' => 'Bangalore',
                        'state' => 'Karnataka',
                        'pincode' => '560029',
                        'country' => 'India',
                    ],
                    'branding' => [
                        'primary_color' => '#059669',
                        'secondary_color' => '#3b82f6',
                    ],
                ],
                'addon_security' => true,
                'addon_sports' => true,
                'addon_laundry' => true,
            ],
            [
                'code' => 'ANUN',
                'name' => 'Anna University Regional Campus',
                'subdomain' => 'annauniv',
                'status' => 'active',
                'settings' => [
                    'contact' => [
                        'name' => 'Dr. Suresh Babu',
                        'phone' => '+919876543213',
                        'email' => 'admin@annauniv.edu',
                    ],
                    'address' => [
                        'street' => 'Sardar Patel Road',
                        'city' => 'Chennai',
                        'state' => 'Tamil Nadu',
                        'pincode' => '600025',
                        'country' => 'India',
                    ],
                    'branding' => [
                        'primary_color' => '#7c3aed',
                        'secondary_color' => '#ec4899',
                    ],
                ],
                'addon_security' => true,
                'addon_sports' => false,
                'addon_laundry' => false,
            ],
            [
                'code' => 'DLART',
                'name' => 'Delhi College of Arts',
                'subdomain' => 'delhiarts',
                'status' => 'active',
                'settings' => [
                    'contact' => [
                        'name' => 'Dr. Amit Verma',
                        'phone' => '+919876543214',
                        'email' => 'admin@delhiarts.ac.in',
                    ],
                    'address' => [
                        'street' => 'Kashmere Gate',
                        'city' => 'Delhi',
                        'state' => 'Delhi',
                        'pincode' => '110006',
                        'country' => 'India',
                    ],
                    'branding' => [
                        'primary_color' => '#ea580c',
                        'secondary_color' => '#0891b2',
                    ],
                ],
                'addon_security' => false,
                'addon_sports' => true,
                'addon_laundry' => true,
            ],
        ];

        foreach ($tenants as $tenantData) {
            // Check if tenant already exists
            $existing = Tenant::where('code', $tenantData['code'])->first();
            
            if (!$existing) {
                $tenant = Tenant::create($tenantData);
                $this->command->info("✅ Created tenant: {$tenant->name} ({$tenant->subdomain})");
                
                // Create tenant database (provision)
                try {
                    $tenant->domains()->create([
                        'domain' => "{$tenant->subdomain}.mapservices.in",
                    ]);
                    
                    // Run tenant migrations
                    $tenant->run(function () {
                        $this->command->info("   → Running migrations for tenant database...");
                        // Migrations will run automatically via Stancl/Tenancy
                    });
                    
                    $this->command->info("   → Provisioned tenant database");
                } catch (\Exception $e) {
                    $this->command->warn("   ⚠️  Could not provision tenant database: {$e->getMessage()}");
                }
            } else {
                $this->command->warn("⚠️  Tenant {$tenantData['code']} already exists, skipping...");
            }
        }

        $this->command->info("\n✅ Demo tenants seeding complete!");
        $this->command->info("Total tenants: " . Tenant::count());
    }
}

