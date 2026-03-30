<?php

namespace Database\Seeders;

use App\Models\Hostel;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Production Students Seeder
 * 
 * Creates 200-300 students per tenant with Indian names, addresses, and room allocations.
 */
class ProductionStudentsSeeder extends Seeder
{
    /**
     * Indian names
     */
    private array $indianFirstNamesMale = [
        'Aarav', 'Vivaan', 'Aditya', 'Arjun', 'Rohit', 'Kunal', 'Pranav', 'Kabir', 'Ishan', 'Harsh',
        'Rahul', 'Vikram', 'Suresh', 'Rajesh', 'Manoj', 'Deepak', 'Sunil', 'Amit', 'Ravi', 'Kumar',
        'Karthik', 'Siddharth', 'Akash', 'Nikhil', 'Varun', 'Vivek', 'Ankit', 'Raj', 'Siddharth', 'Rohan',
    ];

    private array $indianFirstNamesFemale = [
        'Ananya', 'Aarohi', 'Myra', 'Aditi', 'Ira', 'Saanvi', 'Ishita', 'Riya', 'Kritika', 'Meera',
        'Priya', 'Sneha', 'Pooja', 'Kavya', 'Shreya', 'Neha', 'Divya', 'Anjali', 'Swati', 'Rashmi',
        'Simran', 'Tanvi', 'Aadhya', 'Ishani', 'Avni', 'Diya', 'Anika', 'Sara', 'Zara', 'Kiara',
    ];

    private array $indianLastNames = [
        'Sharma', 'Verma', 'Iyer', 'Reddy', 'Menon', 'Patel', 'Singh', 'Khan', 'Das', 'Nair',
        'Gupta', 'Kulkarni', 'Bhatt', 'Chawla', 'Bose', 'Joshi', 'Agarwal', 'Malhotra', 'Kapoor', 'Saxena',
        'Mehta', 'Desai', 'Rao', 'Pillai', 'Nair', 'Kumar', 'Prasad', 'Mishra', 'Tiwari', 'Yadav',
    ];

    private array $indianCities = [
        'Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad',
        'Jaipur', 'Indore', 'Bhopal', 'Lucknow', 'Kanpur', 'Nagpur', 'Vadodara', 'Surat',
        'Nashik', 'Aurangabad', 'Solapur', 'Kolhapur', 'Thane', 'Patna', 'Coimbatore', 'Varanasi',
    ];

    private array $indianStates = [
        'Maharashtra', 'Delhi', 'Karnataka', 'Telangana', 'Tamil Nadu', 'West Bengal', 'Gujarat', 'Rajasthan',
        'Madhya Pradesh', 'Uttar Pradesh', 'Bihar', 'Punjab', 'Haryana', 'Kerala', 'Odisha', 'Assam',
    ];

    private array $indianPrograms = [
        'B.Tech Computer Science',
        'B.Tech Electronics',
        'B.Tech Mechanical',
        'B.Tech Civil',
        'B.Tech Electrical',
        'B.Sc Physics',
        'B.Sc Chemistry',
        'B.Sc Mathematics',
        'B.Com Accounting',
        'B.Com Finance',
        'BA English',
        'BA History',
        'MBA',
        'MCA',
        'M.Tech Computer Science',
        'M.Tech Electronics',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('👨‍🎓 Creating students for each tenant...');

        $tenants = Tenant::all();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📚 Creating students for {$tenant->name}...");
            
            $hostels = Hostel::where('tenant_id', $tenant->id)->get();
            
            if ($hostels->isEmpty()) {
                $this->command->warn("  ⚠️  No hostels found for {$tenant->name}, skipping students...");
                continue;
            }

            // Create 200-300 students per tenant
            $studentsPerTenant = rand(200, 300);
            $studentsPerHostel = (int) ceil($studentsPerTenant / $hostels->count());
            
            $studentCounter = 1;

            foreach ($hostels as $hostel) {
                $studentsForHostel = min($studentsPerHostel, $studentsPerTenant - $totalCreated);
                $gender = $hostel->gender_mode === 'male' ? 'male' : ($hostel->gender_mode === 'female' ? 'female' : 'male');
                
                // Get available beds for this hostel
                $availableBeds = RoomBed::where('hostel_id', $hostel->id)
                    ->where('status', 'available')
                    ->inRandomOrder()
                    ->limit($studentsForHostel)
                    ->get();

                for ($i = 0; $i < $studentsForHostel && $i < $availableBeds->count(); $i++) {
                    $name = $this->indianName($gender === 'male');
                    $emailSlug = strtolower(str_replace(' ', '.', $name));
                    $email = "{$emailSlug}.{$studentCounter}@{$tenant->subdomain}.ac.in";
                    $phone = $this->fakePhone($tenant->id, $studentCounter);

                    // Create user
                    $user = User::firstOrCreate(
                        ['tenant_id' => $tenant->id, 'phone' => $phone],
                        [
                            'name' => $name,
                            'email' => $email,
                            'kind' => 'student',
                            'password' => Hash::make('Student@123'),
                        ]
                    );

                    // Assign Student role
                    if (!$user->hasRole('Student')) {
                        $role = \Spatie\Permission\Models\Role::firstOrCreate(
                            ['name' => 'Student', 'guard_name' => 'web']
                        );
                        $user->assignRole($role);
                    }

                    // Create student profile
                    $mapStudentId = strtoupper($tenant->code) . date('y') . str_pad((string) $studentCounter, 4, '0', STR_PAD_LEFT);
                    $studentUid = 'UID' . strtoupper($tenant->code) . date('y') . str_pad((string) $studentCounter, 4, '0', STR_PAD_LEFT);
                    $rollNo = 'R' . strtoupper($tenant->code) . date('y') . str_pad((string) $studentCounter, 4, '0', STR_PAD_LEFT);

                    $student = Student::firstOrCreate(
                        ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                        [
                            'hostel_id' => $hostel->id,
                            'map_student_id' => $mapStudentId,
                            'student_uid' => $studentUid,
                            'roll_no' => $rollNo,
                            'program' => $this->indianPrograms[array_rand($this->indianPrograms)],
                            'year_of_study' => rand(1, 4),
                            'admission_year' => date('Y') - rand(0, 3),
                            'guardian' => [
                                'father_name' => $this->indianName(true),
                                'mother_name' => $this->indianName(false),
                                'father_phone' => $this->fakePhone($tenant->id, $studentCounter + 10000),
                                'mother_phone' => $this->fakePhone($tenant->id, $studentCounter + 20000),
                                'emergency_contact' => $this->fakePhone($tenant->id, $studentCounter + 30000),
                            ],
                            'correspondence_address' => $this->indianAddress(),
                            'medical_notes' => rand(1, 10) > 8 ? ['blood_group' => $this->getRandomBloodGroup()] : null,
                        ]
                    );

                    // Allocate room/bed
                    if ($i < $availableBeds->count()) {
                        $bed = $availableBeds[$i];
                        
                        // Check if bed is already allocated
                        $existingAllocation = RoomAllocation::where('room_bed_id', $bed->id)
                            ->where('is_active', true)
                            ->first();

                        if (!$existingAllocation) {
                            RoomAllocation::create([
                                'student_id' => $student->id,
                                'room_bed_id' => $bed->id,
                                'hostel_id' => $hostel->id,
                                'effective_from' => now()->subMonths(rand(1, 12)),
                                'is_active' => true,
                            ]);

                            // Update bed status
                            $bed->update([
                                'status' => 'occupied',
                                'occupied_at' => now()->subMonths(rand(1, 12)),
                            ]);
                        }
                    }

                    $studentCounter++;
                    $totalCreated++;

                    if ($totalCreated % 50 == 0) {
                        $this->command->info("  ✅ Created {$totalCreated} students...");
                    }
                }
            }

            $this->command->info("  ✅ Completed: {$totalCreated} students for {$tenant->name}");
        }

        $this->command->info("\n✅ Production students seeding complete!");
        $this->command->info("Total students created: {$totalCreated}");
        $this->command->info("Demo password for all students: Student@123");
    }

    /**
     * Generate Indian name
     */
    private function indianName(bool $male = true): string
    {
        $first = $male 
            ? $this->indianFirstNamesMale[array_rand($this->indianFirstNamesMale)]
            : $this->indianFirstNamesFemale[array_rand($this->indianFirstNamesFemale)];
        $last = $this->indianLastNames[array_rand($this->indianLastNames)];

        return "{$first} {$last}";
    }

    /**
     * Generate fake Indian phone number
     */
    private function fakePhone(string $tenantId, int $index): string
    {
        $base = 9000000000 + (crc32($tenantId) % 100000000) + ($index * 7);
        return '+91' . $base;
    }

    /**
     * Generate Indian address
     */
    private function indianAddress(): array
    {
        $cityIndex = array_rand($this->indianCities);
        
        return [
            'city' => $this->indianCities[$cityIndex],
            'state' => $this->indianStates[$cityIndex % count($this->indianStates)],
            'pincode' => (string) rand(100000, 999999),
            'street' => rand(1, 999) . ', ' . ['MG Road', 'Park Street', 'Main Street', 'Gandhi Nagar', 'Nehru Road'][array_rand(['MG Road', 'Park Street', 'Main Street', 'Gandhi Nagar', 'Nehru Road'])],
        ];
    }

    /**
     * Get random blood group
     */
    private function getRandomBloodGroup(): string
    {
        $groups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
        return $groups[array_rand($groups)];
    }
}

