<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\Hostel;
use App\Models\Room;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoStudentsSeeder extends Seeder
{
    /**
     * Seed demo students for each tenant.
     * Creates 50 students per tenant with profiles, hostel/room assignments.
     */
    public function run(): void
    {
        $tenants = Tenant::whereIn('code', ['STXAV', 'NITKT', 'CHRUN', 'ANUN', 'DLART'])->get();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n👨‍🎓 Creating students for {$tenant->name}...");
            
            // Get available hostels for this tenant
            $tenant->run(function () use ($tenant, &$totalCreated) {
                $hostels = Hostel::all();
                
                if ($hostels->isEmpty()) {
                    $this->command->warn("  ⚠️  No hostels found for {$tenant->name}, skipping students...");
                    return;
                }

                // Create 50 students (in batches for performance)
                for ($i = 1; $i <= 50; $i++) {
                    // Use map_student_id and roll_no (actual schema columns)
                    $mapStudentId = strtoupper($tenant->code) . '2024' . str_pad($i, 3, '0', STR_PAD_LEFT);
                    $rollNo = 'ROLL' . str_pad($i, 3, '0', STR_PAD_LEFT);
                    $email = "student{$i}@{$tenant->subdomain}.edu";

                    // Check if student record already exists in tenant database
                    $existingStudent = Student::where('map_student_id', $mapStudentId)->first();
                    if ($existingStudent) {
                        continue; // Skip if already exists
                    }

                    // Determine gender based on number (30 male, 20 female for realistic distribution)
                    $isMale = $i <= 30;
                    $gender = $isMale ? 'male' : 'female';
                    
                    // Pick appropriate hostel
                    $hostel = $hostels->first(function($h) use ($gender) {
                        return $h->gender_mode === $gender || $h->gender_mode === 'coed';
                    });
                    
                    if (!$hostel) {
                        $hostel = $hostels->first(); // Fallback to any hostel
                    }

                    // Generate realistic Indian name
                    $name = $this->generateStudentName($gender, $i);
                    
                    // STEP 1: Create user in CENTRAL database
                    // Force User model to use 'pgsql' (central) connection explicitly
                    $userId = \DB::connection('pgsql')->transaction(function() use ($tenant, $name, $email, $i) {
                        // Check existing on central DB
                        $existingUser = User::on('pgsql')->where('email', $email)->first();
                        if ($existingUser) {
                            return $existingUser->id;
                        }
                        
                        // Create on central DB
                        $user = User::on('pgsql')->create([
                            'tenant_id' => $tenant->id,
                            'name' => $name,
                            'email' => $email,
                            'phone' => '+919' . rand(100000000, 999999999),
                            'kind' => 'student',
                            'password' => Hash::make('Student@123'),
                        ]);
                        return $user->id;
                    });

                    // STEP 2: Create student record in TENANT database (we're already in tenant context)
                    // Using actual schema: map_student_id, student_uid, roll_no, program, etc.
                    Student::create([
                        'user_id' => (string)$userId, // Store as string (no FK constraint)
                        'map_student_id' => $mapStudentId, // Unique identifier
                        'student_uid' => 'UID' . strtoupper(substr(md5($mapStudentId), 0, 8)), // Generate UID
                        'roll_no' => $rollNo,
                        'hostel_id' => $hostel->id,
                        'program' => $this->getRandomProgram(),
                        'year_of_study' => rand(1, 4),
                        'admission_year' => 2024,
                        'hostel_fee_paid' => (bool)rand(0, 1),
                        'payment_mode' => ['cash', 'upi', 'card', 'bank'][array_rand(['cash', 'upi', 'card', 'bank'])],
                        'payment_amount' => rand(50000, 150000),
                        'payment_date' => now()->subDays(rand(1, 30)),
                        'guardian' => [ // Will be encrypted by model
                            'father_name' => $this->generateFatherName(),
                            'mother_name' => $this->generateMotherName(),
                            'emergency_contact' => '+919' . rand(100000000, 999999999),
                        ],
                        'correspondence_address' => [
                            'city' => $this->getRandomCity(),
                            'state' => $this->getRandomState(),
                            'pincode' => (string)rand(100000, 999999),
                        ],
                    ]);

                    $totalCreated++;
                    
                    if ($i % 10 == 0) {
                        $this->command->info("  ✅ Created {$i} students...");
                    }
                }
                
                $this->command->info("  ✅ Completed: 50 students for {$tenant->name}");
            });
        }

        $this->command->info("\n✅ Demo students seeding complete!");
        $this->command->info("Total students created: {$totalCreated}");
        $this->command->info("Demo password for all students: Student@123");
    }

    /**
     * Generate realistic Indian student names
     */
    private function generateStudentName(string $gender, int $index): string
    {
        $maleFirstNames = ['Aarav', 'Arjun', 'Rohan', 'Karthik', 'Aditya', 'Rahul', 'Vikram', 'Siddharth', 'Akash', 'Nikhil', 'Pranav', 'Varun', 'Vivek', 'Ankit', 'Raj'];
        $femaleFirstNames = ['Ananya', 'Priya', 'Sneha', 'Divya', 'Kavya', 'Pooja', 'Riya', 'Shruti', 'Anjali', 'Neha', 'Simran', 'Tanvi', 'Meera', 'Ishita', 'Aadhya'];
        $lastNames = ['Sharma', 'Patel', 'Kumar', 'Singh', 'Reddy', 'Gupta', 'Nair', 'Iyer', 'Desai', 'Mehta', 'Chatterjee', 'Kulkarni', 'Agarwal', 'Joshi', 'Rao'];

        $firstName = $gender === 'male' 
            ? $maleFirstNames[$index % count($maleFirstNames)]
            : $femaleFirstNames[$index % count($femaleFirstNames)];
        
        $lastName = $lastNames[$index % count($lastNames)];
        
        return "{$firstName} {$lastName}";
    }

    private function generateFatherName(): string
    {
        $names = ['Rajesh', 'Suresh', 'Ramesh', 'Mahesh', 'Dinesh', 'Ganesh', 'Naresh', 'Mukesh'];
        $lastNames = ['Kumar', 'Singh', 'Sharma', 'Patel', 'Reddy', 'Gupta'];
        return $names[array_rand($names)] . ' ' . $lastNames[array_rand($lastNames)];
    }

    private function generateMotherName(): string
    {
        $names = ['Sunita', 'Kavita', 'Anita', 'Geeta', 'Mamta', 'Rekha', 'Usha', 'Radha'];
        $lastNames = ['Devi', 'Kumari', 'Sharma', 'Patel', 'Reddy', 'Gupta'];
        return $names[array_rand($names)] . ' ' . $lastNames[array_rand($lastNames)];
    }

    private function getRandomBloodGroup(): string
    {
        $groups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
        return $groups[array_rand($groups)];
    }

    private function getRandomCity(): string
    {
        $cities = ['Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad', 'Jaipur', 'Lucknow'];
        return $cities[array_rand($cities)];
    }

    private function getRandomState(): string
    {
        $states = ['Maharashtra', 'Delhi', 'Karnataka', 'Telangana', 'Tamil Nadu', 'West Bengal', 'Gujarat', 'Rajasthan', 'Uttar Pradesh'];
        return $states[array_rand($states)];
    }

    private function getRandomProgram(): string
    {
        $programs = [
            'B.Tech Computer Science',
            'B.Tech Electronics',
            'B.Tech Mechanical',
            'B.Tech Civil',
            'B.Sc Physics',
            'B.Sc Chemistry',
            'B.Com Accounting',
            'BA English',
            'MBA',
            'MCA',
        ];
        return $programs[array_rand($programs)];
    }
}

