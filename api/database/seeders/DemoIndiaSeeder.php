<?php

namespace Database\Seeders;

use App\Domain\Tickets\Models\Ticket;
use App\Models\Campus;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomBed;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoIndiaSeeder extends Seeder
{
    // Enhanced Indian datasets with more authentic names and places
    private array $indianFirstNamesMale = ['Aarav', 'Vivaan', 'Aditya', 'Arjun', 'Rohit', 'Kunal', 'Pranav', 'Kabir', 'Ishan', 'Harsh', 'Rahul', 'Vikram', 'Suresh', 'Rajesh', 'Manoj', 'Deepak', 'Sunil', 'Amit', 'Ravi', 'Kumar'];

    private array $indianFirstNamesFemale = ['Ananya', 'Aarohi', 'Myra', 'Aditi', 'Ira', 'Saanvi', 'Ishita', 'Riya', 'Kritika', 'Meera', 'Priya', 'Sneha', 'Pooja', 'Kavya', 'Shreya', 'Neha', 'Divya', 'Anjali', 'Swati', 'Rashmi'];

    private array $indianLastNames = ['Sharma', 'Verma', 'Iyer', 'Reddy', 'Menon', 'Patel', 'Singh', 'Khan', 'Das', 'Nair', 'Gupta', 'Kulkarni', 'Bhatt', 'Chawla', 'Bose', 'Joshi', 'Agarwal', 'Malhotra', 'Kapoor', 'Saxena'];

    private array $indianCities = ['Pune', 'Bengaluru', 'Hyderabad', 'Chennai', 'Mumbai', 'Delhi', 'Kolkata', 'Ahmedabad', 'Jaipur', 'Indore', 'Bhopal', 'Lucknow', 'Kanpur', 'Nagpur', 'Vadodara', 'Surat', 'Nashik', 'Aurangabad', 'Solapur', 'Kolhapur'];

    private array $indianStates = ['MH', 'KA', 'TS', 'TN', 'MH', 'DL', 'WB', 'GJ', 'RJ', 'MP', 'MP', 'UP', 'UP', 'MH', 'GJ', 'GJ', 'MH', 'MH', 'MH', 'MH'];

    private function fakePhone(): string
    {
        return '+91'.rand(6, 9).str_pad((string) rand(0, 999999999), 9, '0', STR_PAD_LEFT);
    }

    private function indianName(bool $male = true): string
    {
        $first = $male ? $this->indianFirstNamesMale[array_rand($this->indianFirstNamesMale)]
                       : $this->indianFirstNamesFemale[array_rand($this->indianFirstNamesFemale)];
        $last = $this->indianLastNames[array_rand($this->indianLastNames)];

        return "{$first} {$last}";
    }

    private function indianAddress(): array
    {
        $i = array_rand($this->indianCities);

        return [
            'city' => $this->indianCities[$i],
            'state' => $this->indianStates[$i],
            'pincode' => str_pad((string) rand(110000, 860000), 6, '0', STR_PAD_LEFT),
        ];
    }

    private function createRoles(): void
    {
        // Create basic roles if they don't exist
        $roles = ['Student', 'Guard', 'Warden', 'Supervisor', 'Admin'];
        foreach ($roles as $roleName) {
            if (! \Spatie\Permission\Models\Role::where('name', $roleName)->exists()) {
                \Spatie\Permission\Models\Role::create(['name' => $roleName, 'guard_name' => 'web']);
            }
        }
    }

    public function run(): void
    {
        DB::transaction(function () {
            $this->createRoles();
            // Tenants (colleges)
            $tenants = collect([
                ['name' => 'Saraswati Institute of Technology', 'code' => 'SIT'],
                ['name' => 'Nalanda University (West Campus)', 'code' => 'NUW'],
                ['name' => 'Vidya Bharati College', 'code' => 'VBC'],
            ])->map(function ($t) {
                return Tenant::firstOrCreate(['code' => $t['code']], ['name' => $t['name']]);
            });

            foreach ($tenants as $tenant) {
                $this->seedTenant($tenant);
            }

            // Add extra tenants for more realistic demo data
            $this->createExtraTenants();

            // Add P2/P3 seed data
            $this->seedP2P3Extras();
        });
    }

    private function seedTenant(Tenant $tenant): void
    {
        // Create a campus for this tenant
        $campus = Campus::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'MAIN'],
            ['name' => 'Main Campus', 'address' => ['city' => 'Pune', 'state' => 'Maharashtra']]
        );

        // Two hostels per tenant
        $hostels = [
            Hostel::firstOrCreate(
                ['tenant_id' => $tenant->id, 'campus_id' => $campus->id, 'code' => 'H1'],
                ['name' => 'H1 Boys Hostel', 'gender_mode' => 'male', 'curfew_time' => '22:00', 'overnight_enabled' => true]
            ),
            Hostel::firstOrCreate(
                ['tenant_id' => $tenant->id, 'campus_id' => $campus->id, 'code' => 'H2'],
                ['name' => 'H2 Girls Hostel', 'gender_mode' => 'female', 'curfew_time' => '21:00', 'overnight_enabled' => true]
            ),
        ];

        foreach ($hostels as $hostel) {
            // Rooms: 40 per hostel; 3 beds each
            for ($r = 101; $r < 141; $r++) {
                $room = Room::firstOrCreate([
                    'tenant_id' => $tenant->id, 'campus_id' => $campus->id, 'hostel_id' => $hostel->id, 'number' => (string) $r,
                ], [
                    'capacity' => 3, 'is_active' => true,
                ]);
                foreach (['A', 'B', 'C'] as $suf) {
                    RoomBed::firstOrCreate([
                        'tenant_id' => $tenant->id, 'hostel_id' => $hostel->id, 'room_id' => $room->id, 'code' => $r.$suf,
                    ]);
                }
            }
        }

        // Create 240 students per tenant (120 boys, 120 girls)
        $studentsPerHostel = 120;
        $this->createStudents($tenant, $hostels[0], $studentsPerHostel, 'M');
        $this->createStudents($tenant, $hostels[1], $studentsPerHostel, 'F');

        // Today out-passes (approved) for guard testing
        $this->createOutPasses($tenant, $hostels);

        // Tickets mix (open/in_progress)
        $this->createTickets($tenant, $hostels);
    }

    private function createStudents(Tenant $tenant, Hostel $hostel, int $count, string $gender): void
    {
        $domain = strtolower($tenant->code).'.demo.edu.in';
        $i = $gender === 'M' ? 1 : 1001; // Different starting points for male/female

        // Collect available beds
        $beds = RoomBed::where('tenant_id', $tenant->id)->where('hostel_id', $hostel->id)->pluck('id')->all();
        shuffle($beds);

        for ($k = 0; $k < $count && $k < count($beds); $k++) {
            $name = $this->indianName($gender === 'M');
            $emailSlug = strtolower(str_replace(' ', '.', $name));
            $email = $emailSlug.$i.'@'.$domain;

            $user = User::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => $email],
                [
                    'name' => $name,
                    'password' => bcrypt(config('app.demo_password', 'demo123')),
                    'phone' => $this->fakePhone(),
                    'kind' => 'student',
                ]
            );
            // attach Student role if using spatie roles
            if (method_exists($user, 'assignRole')) {
                $user->assignRole('Student');
            }

            // create Student profile if model exists
            if (class_exists(\App\Models\Student::class)) {
                $student = \App\Models\Student::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                    [
                        'map_student_id' => 'MAP'.$tenant->code.date('y').str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                        'student_uid' => 'UID'.$tenant->code.date('y').str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                        'roll_no' => 'R'.$tenant->code.date('y').str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                        'program' => 'B.Tech',
                        'year_of_study' => random_int(1, 4),
                        'admission_year' => date('Y') - random_int(0, 3),
                    ]
                );

                // room allocation
                $bedId = $beds[$k] ?? null;
                if ($bedId) {
                    // Check if bed is already allocated
                    $existingAllocation = DB::table('room_allocations')
                        ->where('tenant_id', $tenant->id)
                        ->where('room_bed_id', $bedId)
                        ->where('is_active', true)
                        ->first();

                    if (! $existingAllocation) {
                        DB::table('room_allocations')->insert([
                            'tenant_id' => $tenant->id,
                            'student_id' => $student->id,
                            'hostel_id' => $hostel->id,
                            'room_bed_id' => $bedId,
                            'effective_from' => now(),
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            $i++;
        }
    }

    private function createOutPasses(Tenant $tenant, array $hostels): void
    {
        $today = now('Asia/Kolkata')->startOfDay()->addHours(10);
        foreach ($hostels as $hostel) {
            // pick 10 random students in this hostel
            $students = DB::table('room_allocations')
                ->where('tenant_id', $tenant->id)->where('hostel_id', $hostel->id)
                ->inRandomOrder()->limit(10)->pluck('student_id');
            foreach ($students as $sid) {
                OutPass::firstOrCreate([
                    'tenant_id' => $tenant->id, 'student_id' => $sid, 'requested_at' => $today,
                ], [
                    'hostel_id' => $hostel->id,
                    'valid_until' => $today->clone()->addHours(6),
                    'reason' => 'normal', 'status' => 'approved', 'decided_at' => now(),
                ]);
            }
        }
    }

    private function createTickets(Tenant $tenant, array $hostels): void
    {
        $cats = ['housekeeping', 'maintenance', 'security', 'laundry', 'other'];
        $descriptions = [
            'Water leakage in bathroom',
            'Broken window latch',
            'Door lock not working',
            'Light fixture needs repair',
            'Bed frame is loose',
            'Wardrobe door off track',
            'Fan speed control issue',
            'Power socket not working',
            'Curtain rod falling',
            'Mirror cracked',
        ];
        foreach ($hostels as $hostel) {
            for ($t = 0; $t < 10; $t++) {
                Ticket::firstOrCreate([
                    'tenant_id' => $tenant->id, 'hostel_id' => $hostel->id, 'title' => 'Room issue #'.$t,
                ], [
                    'category' => $cats[array_rand($cats)],
                    'priority' => ['low', 'medium', 'high'][array_rand(['low', 'medium', 'high'])],
                    'status' => ['open', 'in_progress'][array_rand(['open', 'in_progress'])],
                    'description' => $descriptions[array_rand($descriptions)],
                    'reporter_user_id' => 1,
                    'created_by_user_id' => 1,
                ]);
            }
        }
    }

    private function createExtraTenants(): void
    {
        // Add 2 more colleges: SJC & MITS
        $tenants = [
            ['code' => 'SJC',  'name' => 'St. Joseph College',       'domains' => ['sjc.local']],
            ['code' => 'MITS', 'name' => 'Madhav Institute of Tech', 'domains' => ['mits.local']],
        ];

        foreach ($tenants as $t) {
            $tenant = \App\Models\Tenant::firstOrCreate(['code' => $t['code']], [
                'name' => $t['name'],
            ]);

            // One campus
            $campus = \App\Models\Campus::firstOrCreate([
                'tenant_id' => $tenant->id,
                'code' => 'MAIN',
            ], ['name' => "{$t['name']} Main Campus", 'address' => $this->indianAddress()]);

            // 2 hostels (Boys/Girls)
            $hostels = [];
            foreach (['Boys', 'Girls'] as $idx => $kind) {
                $hostels[$kind] = \App\Models\Hostel::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'campus_id' => $campus->id,
                    'code' => 'H'.($idx + 1),
                ], [
                    'name' => "Hostel {$kind}",
                    'gender_mode' => $kind === 'Boys' ? 'male' : 'female',
                    'curfew_time' => $kind === 'Boys' ? '22:00' : '21:00',
                    'overnight_enabled' => true,
                ]);
            }

            // Rooms & beds (60 rooms / hostel, 3 beds each)
            foreach ($hostels as $kind => $hostel) {
                for ($r = 1; $r <= 60; $r++) {
                    $room = \App\Models\Room::firstOrCreate([
                        'tenant_id' => $tenant->id,
                        'campus_id' => $campus->id,
                        'hostel_id' => $hostel->id,
                        'number' => sprintf('%s-%03d', $hostel->code, $r),
                    ], [
                        'capacity' => 3,
                        'is_active' => true,
                    ]);
                    for ($b = 1; $b <= 3; $b++) {
                        \App\Models\RoomBed::firstOrCreate([
                            'tenant_id' => $tenant->id,
                            'hostel_id' => $hostel->id,
                            'room_id' => $room->id,
                            'code' => sprintf('%s-%03d-%d', $hostel->code, $r, $b),
                        ], []);
                    }
                }
            }

            // 200 students per tenant (100 male, 100 female)
            $counter = 1;
            foreach (['Boys' => true, 'Girls' => false] as $kind => $male) {
                $hostel = $hostels[$kind];
                $beds = \App\Models\RoomBed::where('hostel_id', $hostel->id)->pluck('id')->all();
                shuffle($beds);

                for ($i = 0; $i < 100; $i++) {
                    $name = $this->indianName($male);
                    $emailSlug = strtolower(str_replace(' ', '.', $name));
                    $addr = $this->indianAddress();

                    $user = \App\Models\User::firstOrCreate([
                        'tenant_id' => $tenant->id,
                        'email' => "{$emailSlug}.{$t['code']}{$counter}@example.com",
                    ], [
                        'name' => $name,
                        'kind' => 'student',
                        'phone' => $this->fakePhone(),
                        'password' => bcrypt(config('app.demo_password', 'demo123')),
                    ]);

                    // Ensure role "Student" exists and assign
                    if (method_exists($user, 'assignRole')) {
                        $user->assignRole('Student');
                    }

                    $student = \App\Models\Student::firstOrCreate([
                        'tenant_id' => $tenant->id,
                        'user_id' => $user->id,
                    ], [
                        'student_uid' => strtoupper($t['code']).'-'.str_pad((string) $counter, 6, '0', STR_PAD_LEFT),
                        'map_student_id' => 'MAP'.$t['code'].date('y').str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
                        'roll_no' => 'R'.$t['code'].str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
                        'program' => 'B.Tech',
                        'year_of_study' => random_int(1, 4),
                        'admission_year' => date('Y') - random_int(0, 3),
                        'correspondence_address' => $addr,
                    ]);

                    // Allocation
                    $bedId = $beds[$i] ?? null;
                    if ($bedId) {
                        // Check if bed is already allocated
                        $existingAllocation = DB::table('room_allocations')
                            ->where('tenant_id', $tenant->id)
                            ->where('room_bed_id', $bedId)
                            ->where('is_active', true)
                            ->first();

                        if (! $existingAllocation) {
                            DB::table('room_allocations')->insert([
                                'tenant_id' => $tenant->id,
                                'student_id' => $student->id,
                                'hostel_id' => $hostel->id,
                                'room_bed_id' => $bedId,
                                'effective_from' => now()->subMonths(rand(1, 9)),
                                'is_active' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            \App\Models\RoomBed::whereKey($bedId)->update(['occupied_at' => now()]);
                        }
                    }

                    $counter++;
                }
            }

            // A few out-passes & tickets today
            $someStudents = \App\Models\Student::where('tenant_id', $tenant->id)->inRandomOrder()->limit(20)->get();
            foreach ($someStudents as $s) {
                \App\Models\Domain\OutPass\OutPass::create([
                    'tenant_id' => $tenant->id,
                    'student_id' => $s->id,
                    'hostel_id' => $s->hostel_id ?? $hostels['Boys']->id,
                    'reason' => 'normal',
                    'requested_at' => now()->addHour(),
                    'valid_until' => now()->addHours(6),
                    'status' => 'approved',
                    'decided_at' => now(),
                ]);
            }

            foreach (['housekeeping', 'maintenance', 'security', 'laundry', 'other'] as $cat) {
                \App\Domain\Tickets\Models\Ticket::create([
                    'tenant_id' => $tenant->id,
                    'hostel_id' => $hostels['Boys']->id,
                    'title' => ucfirst($cat).' issue',
                    'description' => 'Autogenerated for UAT',
                    'category' => $cat,
                    'priority' => ['low', 'medium', 'high'][array_rand([0, 1, 2])],
                    'status' => 'open',
                    'created_by_user_id' => \App\Models\User::where('tenant_id', $tenant->id)->inRandomOrder()->value('id'),
                ]);
            }
        }
    }

    private function seedP2P3Extras(): void
    {
        // Add P2/P3 seed data for all tenants
        $tenants = Tenant::all();
        foreach ($tenants as $tenant) {
            // Laundry cycles
            $hostels = Hostel::where('tenant_id', $tenant->id)->take(2)->get();
            if ($hostels->count() >= 2) {
                DB::table('laundry_cycles')->insert([
                    [
                        'tenant_id' => $tenant->id,
                        'hostel_id' => $hostels[0]->id,
                        'machine_label' => 'Machine A1',
                        'status' => 'scheduled',
                        'started_at' => null,
                        'completed_at' => null,
                        'metadata' => json_encode(['batch' => 'A']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'tenant_id' => $tenant->id,
                        'hostel_id' => $hostels[1]->id,
                        'machine_label' => 'Machine B1',
                        'status' => 'in_progress',
                        'started_at' => now()->subHour(),
                        'completed_at' => null,
                        'metadata' => json_encode(['batch' => 'B']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }

            // Sports events
            $campus = Campus::where('tenant_id', $tenant->id)->first();
            if ($campus && $hostels->count() >= 1) {
                DB::table('sports_events')->insert([
                    [
                        'tenant_id' => $tenant->id,
                        'campus_id' => $campus->id,
                        'hostel_id' => $hostels[0]->id,
                        'sport' => 'badminton',
                        'name' => 'Badminton Doubles',
                        'scheduled_at' => now()->addDays(1),
                        'venue' => 'Sports Complex',
                        'status' => 'scheduled',
                        'capacity' => 4,
                        'metadata' => json_encode(['type' => 'doubles']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'tenant_id' => $tenant->id,
                        'campus_id' => $campus->id,
                        'hostel_id' => $hostels[0]->id,
                        'sport' => 'cricket',
                        'name' => 'Cricket Nets',
                        'scheduled_at' => now()->addDays(2),
                        'venue' => 'Cricket Ground',
                        'status' => 'scheduled',
                        'capacity' => 12,
                        'metadata' => json_encode(['type' => 'practice']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }
        }
    }
}
