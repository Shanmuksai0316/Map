<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Super Admin Display Data Seeder
 * 
 * Adds sample data to CENTRAL database for Super Admin views.
 * This makes all Super Admin pages show meaningful data.
 */
class SuperAdminDisplayDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎨 Adding display data to Super Admin pages...');
        $this->command->newLine();

        // Get existing tenants
        $tenants = Tenant::all();
        
        if ($tenants->isEmpty()) {
            $this->command->error('No tenants found! Please create tenants first.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->command->info("Adding data for: {$tenant->name}");
            
            // Create campuses in CENTRAL database
            $campusCount = Campus::where('tenant_id', $tenant->id)->count();
            
            if ($campusCount == 0) {
                $campus = Campus::create([
                    'tenant_id' => $tenant->id,
                    'code' => 'MAIN',
                    'name' => 'Main Campus',
                    'address' => [
                        'street' => '123 University Road',
                        'city' => 'New Delhi',
                        'state' => 'Delhi',
                        'pincode' => '110001',
                        'country' => 'India',
                    ],
                ]);
                
                $this->command->info("  ✓ Created campus: {$campus->name}");
                
                // Create hostels in CENTRAL database
                $hostelData = [
                    [
                        'name' => 'Boys Hostel A',
                        'code' => 'BH-01',
                        'gender_mode' => 'Male',
                    ],
                    [
                        'name' => 'Girls Hostel A',
                        'code' => 'GH-01',
                        'gender_mode' => 'Female',
                    ],
                ];
                
                foreach ($hostelData as $data) {
                    $hostel = Hostel::create([
                        'tenant_id' => $tenant->id,
                        'campus_id' => $campus->id,
                        'code' => $data['code'],
                        'name' => $data['name'],
                        'gender_mode' => $data['gender_mode'],
                        'curfew_time' => '22:00:00',
                        'overnight_enabled' => false,
                        'visiting_start' => '16:00:00',
                        'visiting_end' => '19:00:00',
                        'address' => [
                            'street' => 'Campus Road',
                            'city' => 'New Delhi',
                            'state' => 'Delhi',
                            'pincode' => '110001',
                        ],
                    ]);
                    
                    $this->command->info("  ✓ Created hostel: {$hostel->name}");
                }
            } else {
                $this->command->info("  - Campus data already exists");
            }
            
            // Create staff users in CENTRAL database
            $staffCount = User::where('tenant_id', $tenant->id)->where('kind', 'staff')->count();
            
            if ($staffCount < 3) {
                $roles = ['Rector', 'Campus Manager', 'Warden'];
                
                foreach ($roles as $index => $roleName) {
                    $phone = '98765432' . str_pad((string)($index + 10), 2, '0', STR_PAD_LEFT);
                    
                    if (!User::where('phone', $phone)->exists()) {
                        $user = User::create([
                            'tenant_id' => $tenant->id,
                            'name' => "{$roleName} - {$tenant->code}",
                            'phone' => $phone,
                            'email' => strtolower(str_replace(' ', '', $roleName)) . '@' . strtolower($tenant->code) . '.edu',
                            'password' => Hash::make('password'),
                            'kind' => 'staff',
                        ]);
                        
                        // Assign role
                        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
                        $user->assignRole($role);
                        
                        $this->command->info("  ✓ Created staff: {$user->name} ({$roleName})");
                    }
                }
            } else {
                $this->command->info("  - Staff users already exist");
            }
            
            // Create students in CENTRAL database
            $studentCount = Student::where('tenant_id', $tenant->id)->count();
            
            if ($studentCount < 10) {
                for ($i = 1; $i <= 10; $i++) {
                    $phone = '91234567' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                    
                    if (!User::where('phone', $phone)->exists()) {
                        $user = User::create([
                            'tenant_id' => $tenant->id,
                            'name' => "Student {$i} - {$tenant->code}",
                            'phone' => $phone,
                            'email' => "student{$i}@" . strtolower($tenant->code) . '.edu',
                            'password' => Hash::make('password'),
                            'kind' => 'student',
                        ]);
                        
                        Student::create([
                            'tenant_id' => $tenant->id,
                            'user_id' => $user->id,
                            'map_student_id' => 'STD-' . strtoupper($tenant->code) . '-' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
                            'student_uid' => strtoupper($tenant->code) . $i,
                            'roll_no' => '2024' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
                            'program' => 'B.Tech',
                            'year_of_study' => ($i % 4) + 1, // 1, 2, 3, or 4
                            'admission_year' => 2024 - (($i % 4)),
                        ]);
                    }
                }
                $this->command->info("  ✓ Created 10 students");
            } else {
                $this->command->info("  - Students already exist");
            }
        }

        $this->command->newLine();
        $this->command->info('📊 Summary:');
        $this->command->info('  - Tenants: ' . Tenant::count());
        $this->command->info('  - Campuses: ' . Campus::count());
        $this->command->info('  - Hostels: ' . Hostel::count());
        $this->command->info('  - Students: ' . Student::count());
        $this->command->info('  - Staff: ' . User::where('kind', 'staff')->count());
        $this->command->newLine();
        $this->command->info('✅ Super Admin display data seeded successfully!');
    }
}

