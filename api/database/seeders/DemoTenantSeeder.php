<?php

namespace Database\Seeders;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistItem;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Domain\Gate\Models\GateDevice;
use App\Domain\Gate\Models\GateEntry;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketComment;
use App\Domain\Visitors\Models\GuestVisit;
use App\Models\AttendanceLog;
use App\Models\AttendanceMark;
use App\Models\AttendanceSession;
use App\Models\Campus;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Models\LaundryCycle;
use App\Models\LaundryRequest;
use App\Models\Notice;
use App\Models\Room;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\SportsEnrollment;
use App\Models\SportsEvent;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DemoTenantSeeder extends Seeder
{
    protected Tenant $tenant;

    protected Campus $campus;

    protected Hostel $hostelMale;

    protected Hostel $hostelFemale;

    protected array $staff = [];

    protected array $students = [];

    protected array $rooms = [];

    protected array $checklistTemplates = [];

    public function run(): void
    {
        $this->command->info('🏗️  Building DEMO-COLLEGE tenant...');

        $this->createTenant();
        $this->createStaff(); // Create staff in central database (users table)
        
        // Initialize tenancy for all tenant-specific data
        tenancy()->initialize($this->tenant);
        app()->instance('testing.default_tenant_id', $this->tenant->id);

        if (Campus::where('tenant_id', $this->tenant->id)->exists()) {
            $this->command->warn('⚠️  Demo tenant already exists — skipping data regeneration.');
            tenancy()->end();
            app()->forgetInstance('testing.default_tenant_id');
            return;
        }

        try {
            $this->createCampusAndHostels();
            $this->createRoomsAndBeds();
            $this->createStudents();
            $this->allocateStudents();
            $this->createOutPasses();
            $this->createGateEntries();
            $this->createGateDevices();
            $this->createVisitors();
            $this->createAttendance();
            $this->createChecklistTemplates();
            $this->createChecklistInstances();
            $this->createLaundry();
            $this->createSports();
            $this->createNotices();
            $this->createTickets();

            // P1 Mobile UX Demo Data
            $this->createP1StudentOutPasses();
            $this->createP1AttendanceSession();
            $this->createP1SupervisorTickets();
        } finally {
            tenancy()->end();
            app()->forgetInstance('testing.default_tenant_id');
        }

        $this->command->info('✅ Demo data created successfully!');
        $this->command->info('📧 Login credentials written to docs/demo/DemoCredentials_v1.2.md');
    }

    protected function createTenant(): void
    {
        $attributes = [
            'name' => 'Demo College Hostel',
            'addon_security' => true,
            'addon_sports' => true,
            'addon_laundry' => true,
            'settings' => [
                'payments_s3' => true,
                'sms_events' => ['outpass_approved', 'attendance_missed'],
                'checklists_module' => true,
                'gate_device_enforcement' => false,
            ],
        ];

        // Code must satisfy MAP-* prefix constraint (see migration 2025_12_04_000001_add_map_prefix_constraint_to_tenants)
        $this->tenant = Tenant::query()->firstOrNew(['code' => 'MAP-DEMO-COLLEGE']);
        $this->tenant->fill($attributes);
        $this->tenant->save();

        if (!$this->tenant->domains()->where('domain', 'demo-college.localhost')->exists()) {
            $this->tenant->domains()->create([
                'domain' => 'demo-college.localhost',
            ]);
        }
    }

    protected function createStaff(): void
    {
        $staffData = [
            ['name' => 'Super Admin', 'email' => 'super@demo.map.ac.in', 'phone' => '+919900000001', 'kind' => 'Super Admin', 'role' => 'Super Admin'],
            ['name' => 'Campus Manager Demo', 'email' => 'campus@demo.map.ac.in', 'phone' => '+919900000002', 'kind' => 'CampusManager', 'role' => 'Campus Manager'],
            ['name' => 'Rector Demo', 'email' => 'rector@demo.map.ac.in', 'phone' => '+919900000003', 'kind' => 'Rector', 'role' => 'Rector'],
            ['name' => 'College Management Demo', 'email' => 'college@demo.map.ac.in', 'phone' => '+919900000004', 'kind' => 'CollegeMgmt', 'role' => 'College Management'],
            ['name' => 'Warden H1 Demo', 'email' => 'warden.h1@demo.map.ac.in', 'phone' => '+919900000005', 'kind' => 'Warden', 'role' => 'Warden'],
            ['name' => 'Warden H2 Demo', 'email' => 'warden.h2@demo.map.ac.in', 'phone' => '+919900000006', 'kind' => 'Warden', 'role' => 'Warden'],
            ['name' => 'HK Supervisor Demo', 'email' => 'hk@demo.map.ac.in', 'phone' => '+919900000007', 'kind' => 'HKSupervisor', 'role' => 'HK Supervisor'],
            ['name' => 'RM Supervisor Demo', 'email' => 'rm@demo.map.ac.in', 'phone' => '+919900000008', 'kind' => 'RMSupervisor', 'role' => 'RM Supervisor'],
            ['name' => 'Guard Demo', 'email' => 'guard@demo.map.ac.in', 'phone' => '+919900000009', 'kind' => 'Guard', 'role' => 'Guard'],
            ['name' => 'Laundry Manager Demo', 'email' => 'laundry@demo.map.ac.in', 'phone' => '+919900000010', 'kind' => 'LaundryManager', 'role' => 'Laundry Manager'],
            ['name' => 'Sports Manager Demo', 'email' => 'sports@demo.map.ac.in', 'phone' => '+919900000011', 'kind' => 'SportsManager', 'role' => 'Sports Manager'],
        ];

        foreach ($staffData as $data) {
            $user = User::firstOrNew(['phone' => $data['phone']]);
            $user->fill([
                'tenant_id' => $this->tenant->id,
                'email' => $data['email'],
                'name' => $data['name'],
                'kind' => $data['kind'],
                'is_map_staff' => true,
                'password' => Hash::make('demo123'),
            ]);
            $user->save();

            $role = Role::findByName($data['role'], 'web');
            if (!$user->hasRole($role->name, 'web')) {
                $user->assignRole($role);
            }

            $this->staff[$data['kind']] = $user;
        }
    }

    protected function createCampusAndHostels(): void
    {
        // Already in tenant context - no tenant_id needed
        $this->campus = Campus::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'MAIN',
            'name' => 'Main Campus',
            'address' => [
                'line1' => '123 University Road',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'pincode' => '560001',
            ],
        ]);

        $this->hostelMale = Hostel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'campus_id' => $this->campus->id,
            'code' => 'H1',
            'name' => 'Hostel 1 (Boys)',
            'gender_mode' => 'Male',
            'curfew_time' => '22:30:00',
            'overnight_enabled' => false,
            'visiting_start' => '16:00:00',
            'visiting_end' => '19:00:00',
            'settings' => [],
        ]);

        $this->hostelFemale = Hostel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'campus_id' => $this->campus->id,
            'code' => 'H2',
            'name' => 'Hostel 2 (Girls)',
            'gender_mode' => 'Female',
            'curfew_time' => '22:30:00',
            'overnight_enabled' => false,
            'visiting_start' => '16:00:00',
            'visiting_end' => '19:00:00',
            'settings' => [],
        ]);
    }

    protected function createRoomsAndBeds(): void
    {
        $hostels = [$this->hostelMale, $this->hostelFemale];
        $blocks = ['A', 'B'];
        $floors = ['1', '2', '3'];
        $roomsPerFloor = 7;
        $bedsPerRoom = 3;

        foreach ($hostels as $hostel) {
            foreach ($blocks as $block) {
                foreach ($floors as $floor) {
                    for ($roomNum = 1; $roomNum <= $roomsPerFloor; $roomNum++) {
                        $roomNumber = sprintf('%s%s-%03d', $block, $floor, $roomNum);
                        $room = Room::firstOrCreate([
                            'tenant_id' => $this->tenant->id,
                            'hostel_id' => $hostel->id,
                            'block_code' => $block,
                            'floor_code' => $floor,
                            'number' => $roomNumber,
                        ], [
                            'campus_id' => $this->campus->id,
                            'capacity' => 3,
                            'is_active' => true,
                        ]);

                        $this->rooms[] = $room;

                        for ($bedNum = 0; $bedNum < $bedsPerRoom; $bedNum++) {
                            RoomBed::firstOrCreate([
                                'room_id' => $room->id,
                                'code' => chr(65 + $bedNum), // A, B, C
                            ], [
                                'tenant_id' => $this->tenant->id,
                                'hostel_id' => $hostel->id,
                                'status' => 'available',
                            ]);
                        }
                    }
                }
            }
        }
    }

    protected function createStudents(): void
    {
        $studentRole = Role::findByName('Student', 'web');

        // Create male students for male hostel
        for ($i = 1; $i <= 75; $i++) {
            $student = Student::factory()->create([
                'hostel_id' => $this->hostelMale->id,
                'gender' => 'Male',
            ]);

            // Assign Student role
            if (!$student->user->hasRole($studentRole->name, 'web')) {
                $student->user->assignRole($studentRole);
            }

            // Set password for first few students for testing
            if ($i <= 5) {
                $student->user->password = bcrypt(config('app.demo_password', 'demo123'));
                $student->user->save();
            }

            $this->students[] = $student;
        }

        // Create female students for female hostel
        for ($i = 1; $i <= 75; $i++) {
            $student = Student::factory()->create([
                'hostel_id' => $this->hostelFemale->id,
                'gender' => 'Female',
            ]);

            // Assign Student role
            if (!$student->user->hasRole($studentRole->name, 'web')) {
                $student->user->assignRole($studentRole);
            }

            // Set password for first few students for testing
            if ($i <= 5) {
                $student->user->password = bcrypt(config('app.demo_password', 'demo123'));
                $student->user->save();
            }

            $this->students[] = $student;
        }
    }

    protected function allocateStudents(): void
    {
        DB::transaction(function () {
            foreach ($this->students as $student) {
                // Already in tenant context - no need to filter by tenant_id
                $availableBed = RoomBed::where('hostel_id', $student['hostel_id'])
                    ->where('status', 'available')
                    ->whereDoesntHave('allocations', function ($query) {
                        $query->where('is_active', true);
                    })
                    ->first();

                if ($availableBed) {
                    // Mark bed as occupied
                    $availableBed->update(['status' => 'occupied', 'occupied_at' => now()]);

                    // Create allocation
                    RoomAllocation::create([
                        'student_id' => $student['id'],
                        'room_bed_id' => $availableBed->id,
                        'hostel_id' => $student['hostel_id'],
                        'is_active' => true,
                        'effective_from' => now(),
                    ]);
                }
            }
        });
    }

    protected function createOutPasses(): void
    {
        $today = now('Asia/Kolkata');
        $yesterday = $today->copy()->subDay();

        $students = collect($this->students);
        if ($students->isEmpty()) {
            return;
        }

        $createOutPass = function (string $status, \Carbon\Carbon $time, int $count) use ($students): void {
            for ($i = 0; $i < $count; $i++) {
                $student = $students->random();

                OutPass::create([
                    'tenant_id' => $student->tenant_id,
                    'student_id' => $student->id,
                    'hostel_id' => $student->hostel_id,
                    'reason' => fake()->randomElement(['normal', 'leave', 'sick']),
                    'overnight' => fake()->boolean(25),
                    'status' => $status,
                    'requested_at' => $time,
                    'requested_for' => $time->toDateString(),
                    'decided_at' => in_array($status, ['approved', 'declined']) ? $time : null,
                    'valid_until' => $time->copy()->addHours(8),
                    'note' => fake()->optional(0.3)->sentence(),
                    'idempotency_key' => (string) Str::uuid(),
                    'decision_by' => $status === 'approved' ? $this->staff['Rector']->id : null,
                ]);
            }
        };

        $createOutPass('approved', $today, 15);
        $createOutPass('approved', $today->copy()->addHours(2), 10);
        $createOutPass('pending', $today->copy()->addHours(4), 15);
        $createOutPass('approved', $yesterday, 10);
    }

    protected function createGateEntries(): void
    {
        $today = now();
        $students = collect($this->students);

        if ($students->isEmpty()) {
            return;
        }

        $hostels = Hostel::whereIn('id', [$this->hostelMale->id, $this->hostelFemale->id])
            ->get()
            ->keyBy('id');

        $createEntry = function (string $event, string $direction, int $count) use ($students, $today, $hostels): void {
            for ($i = 0; $i < $count; $i++) {
                $student = $students->random();
                $hostel = $hostels->get($student->hostel_id) ?? $student->hostel;
                $occurredAt = $today->copy()->subHours(rand(1, 8))->subMinutes(rand(5, 45));
                $wasOffline = fake()->boolean(25);
                $syncedAt = $wasOffline ? $occurredAt->copy()->addMinutes(rand(5, 15)) : $occurredAt->copy()->addMinute();

                GateEntry::create([
                    'tenant_id' => $student->tenant_id,
                    'campus_id' => $hostel?->campus_id,
                    'hostel_id' => $hostel?->id,
                    'student_id' => $student->id,
                    'event' => $event,
                    'direction' => $direction,
                    'method' => fake()->randomElement(['qr', 'otp', 'manual']),
                    'source' => $wasOffline ? 'device_offline' : 'mobile',
                    'verified' => true,
                    'verified_at' => $occurredAt->copy()->addMinute(),
                    'occurred_at' => $occurredAt,
                    'client_reference' => (string) Str::uuid(),
                    'was_offline' => $wasOffline,
                    'synced_at' => $syncedAt,
                    'late_minutes' => $direction === 'in' ? rand(0, 45) : null,
                    'notes' => $direction === 'in' ? 'Student returned to hostel' : 'Student exited campus',
                    'metadata' => [
                        'device' => $direction === 'in' ? 'Gate Tablet IN' : 'Gate Tablet OUT',
                    ],
                    'guard_user_id' => $this->staff['Guard']->id,
                ]);
            }
        };

        $createEntry('exit', 'out', 30);
        $createEntry('entry', 'in', 15);
    }

    protected function createGateDevices(): void
    {
        GateDevice::updateOrCreate(
            ['device_uuid' => 'DEMO-TABLET-01'],
            [
                'tenant_id' => $this->tenant->id,
                'name' => 'Main Gate Tablet',
                'hostel_id' => $this->hostelMale->id,
                'is_active' => true,
                'last_seen_at' => now(),
                'enrolled_at' => now(),
                'enrolled_by_user_id' => $this->staff['Guard']->id,
            ]
        );

        GateDevice::updateOrCreate(
            ['device_uuid' => 'DEMO-TABLET-02'],
            [
                'tenant_id' => $this->tenant->id,
                'name' => 'Secondary Gate Tablet',
                'hostel_id' => $this->hostelFemale->id,
                'is_active' => false,
                'last_seen_at' => now()->subHours(2),
                'enrolled_at' => now()->subHours(2),
                'enrolled_by_user_id' => $this->staff['Guard']->id,
            ]
        );
    }

    protected function createVisitors(): void
    {
        $today = now();

        $students = collect($this->students);

        if ($students->isEmpty()) {
            return;
        }

        // Create allowed visitors
        for ($i = 0; $i < 15; $i++) {
            $student = $students->random();
            GuestVisit::create([
                'tenant_id' => $student->tenant_id,
                'hostel_id' => $student->hostel_id,
                'student_id' => $student->id,
                'name' => fake()->name(),
                'phone' => fake()->e164PhoneNumber(),
                'whom_to_meet' => 'Visit to ' . ($student->user->name ?? 'Student'),
                'visit_date' => $today->format('Y-m-d'),
                'status' => GuestVisit::STATUS_APPROVED,
                'created_by_user_id' => $student->user_id,
                'allowed_by_user_id' => $this->staff['Guard']->id,
                'allowed_at' => $today->copy()->subHours(2),
            ]);
        }

        // Create pre-registered visitors
        for ($i = 0; $i < 3; $i++) {
            $student = $students->random();
            GuestVisit::create([
                'tenant_id' => $student->tenant_id,
                'hostel_id' => $student->hostel_id,
                'student_id' => $student->id,
                'name' => fake()->name(),
                'phone' => fake()->e164PhoneNumber(),
                'whom_to_meet' => 'Visit to ' . ($student->user->name ?? 'Student'),
                'visit_date' => $today->format('Y-m-d'),
                'status' => GuestVisit::STATUS_PENDING,
                'created_by_user_id' => $student->user_id,
            ]);
        }

        // Create denied visitors
        for ($i = 0; $i < 7; $i++) {
            $student = $students->random();
            GuestVisit::create([
                'tenant_id' => $student->tenant_id,
                'hostel_id' => $student->hostel_id,
                'student_id' => $student->id,
                'name' => fake()->name(),
                'phone' => fake()->e164PhoneNumber(),
                'whom_to_meet' => 'Visit to ' . ($student->user->name ?? 'Student'),
                'visit_date' => $today->format('Y-m-d'),
                'status' => GuestVisit::STATUS_DENIED,
                'created_by_user_id' => $student->user_id,
                'denied_by_user_id' => $this->staff['Guard']->id,
                'denied_at' => $today->copy()->subHour(),
            ]);
        }
    }

    protected function createAttendance(): void
    {
        $today = now();

        // Create attendance sessions for both hostels
        foreach ([$this->hostelMale, $this->hostelFemale] as $hostel) {
            $session = AttendanceSession::factory()->create([
                'tenant_id' => $this->tenant->id,
                'hostel_id' => $hostel->id,
                'session_date' => $today->toDateString(),
                'session_time' => '22:30:00',
                'name' => 'Night Check - '.$hostel->name,
                'kind' => 'night_check',
                'scheduled_at' => $today->copy()->setTime(22, 30),
                'status' => 'in_progress',
            ]);

            // Mark attendance for some students
            $students = Student::where('tenant_id', $this->tenant->id)
                ->where('hostel_id', $hostel->id)
                ->take(30)
                ->get();

            foreach ($students as $student) {
                AttendanceLog::create([
                    'tenant_id' => $this->tenant->id,
                    'hostel_id' => $hostel->id,
                    'attendance_session_id' => $session->id,
                    'attendance_date' => $session->session_date ?? $today->toDateString(),
                    'student_id' => $student->id,
                    'status' => fake()->randomElement(['present', 'absent', 'late']),
                    'marked_at' => $today->copy()->subHours(1),
                    'marked_by' => $this->staff['Warden']->id,
                ]);
            }
        }
    }

    protected function createChecklistTemplates(): void
    {
        $definitions = [
            [
                'key' => 'housekeeping',
                'title' => 'Daily Housekeeping',
                'role' => 'HK Supervisor',
                'tasks' => [
                    ['code' => 'clean_bathrooms', 'label' => 'Clean bathrooms', 'required' => true],
                    ['code' => 'vacuum_floors', 'label' => 'Vacuum floors', 'required' => true],
                    ['code' => 'empty_trash', 'label' => 'Empty trash', 'required' => false],
                ],
                'created_by' => $this->staff['HKSupervisor']->id,
            ],
            [
                'key' => 'maintenance',
                'title' => 'Maintenance Check',
                'role' => 'RM Supervisor',
                'tasks' => [
                    ['code' => 'check_electrical', 'label' => 'Check electrical outlets', 'required' => true],
                    ['code' => 'test_water_pressure', 'label' => 'Test water pressure', 'required' => true],
                    ['code' => 'inspect_windows', 'label' => 'Inspect windows', 'required' => false],
                ],
                'created_by' => $this->staff['RMSupervisor']->id,
            ],
        ];

        foreach ($definitions as $definition) {
            $template = ChecklistTemplate::query()->create([
                'tenant_id' => $this->tenant->id,
                'title' => $definition['title'],
                'role' => $definition['role'],
                'tasks' => $definition['tasks'],
                'active' => true,
                'created_by_user_id' => $definition['created_by'],
            ]);

            $this->checklistTemplates[$definition['key']] = $template;
        }
    }

    protected function createChecklistInstances(): void
    {
        $today = now();

        if ($this->checklistTemplates === []) {
            $this->checklistTemplates = ChecklistTemplate::where('tenant_id', $this->tenant->id)->get()->keyBy('role')->all();
        }

        $hkTemplate = $this->checklistTemplates['housekeeping'] ?? null;
        $rmTemplate = $this->checklistTemplates['maintenance'] ?? null;

        if ($hkTemplate) {
            $instance = ChecklistInstance::query()->create([
                'tenant_id' => $this->tenant->id,
                'template_id' => $hkTemplate->id,
                'date' => $today->format('Y-m-d'),
                'shift' => 'Daily',
                'role' => $hkTemplate->role,
                'assignee_user_id' => $this->staff['HKSupervisor']->id,
                'status' => ChecklistInstance::STATUS_SUBMITTED,
                'review_status' => ChecklistInstance::STATUS_APPROVED,
                'total_tasks' => count($hkTemplate->tasks ?? []),
                'completed_tasks' => count($hkTemplate->tasks ?? []),
                'submitted_at' => $today->copy()->subHour(),
                'manager_user_id' => $this->staff['CampusManager']->id,
                'manager_note' => 'Approved',
                'reviewed_at' => $today->copy()->subMinutes(30),
            ]);

            $this->createChecklistItems($instance, $hkTemplate->tasks ?? [], true);
        }

        if ($rmTemplate) {
            $instance = ChecklistInstance::query()->create([
                'tenant_id' => $this->tenant->id,
                'template_id' => $rmTemplate->id,
                'date' => $today->format('Y-m-d'),
                'shift' => 'Daily',
                'role' => $rmTemplate->role,
                'assignee_user_id' => $this->staff['RMSupervisor']->id,
                'status' => ChecklistInstance::STATUS_PENDING,
                'total_tasks' => count($rmTemplate->tasks ?? []),
                'completed_tasks' => 0,
            ]);

            $this->createChecklistItems($instance, $rmTemplate->tasks ?? []);
        }
    }

    protected function createChecklistItems(ChecklistInstance $instance, array $tasks, bool $markComplete = false): void
    {
        foreach ($tasks as $task) {
            ChecklistItem::query()->create([
                'tenant_id' => $instance->tenant_id,
                'instance_id' => $instance->id,
                'code' => $task['code'] ?? Str::slug($task['label'] ?? $task['name'] ?? 'task'),
                'label' => $task['label'] ?? $task['name'] ?? 'Task',
                'state' => $markComplete ? 'Done' : 'Pending',
                'comment' => $markComplete ? 'Auto-approved for demo' : null,
                'photo_urls' => null,
                'completed_at' => $markComplete ? now() : null,
            ]);
        }
    }

    protected function createLaundry(): void
    {
        $cycle = LaundryCycle::query()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostelMale->id,
            'machine_label' => 'WASH-01',
            'status' => 'in_progress',
            'started_at' => now()->subHours(2),
            'metadata' => [
                'program' => 'standard',
                'temperature' => '40C',
            ],
        ]);

        $students = Student::where('tenant_id', $this->tenant->id)->take(8)->get();
        $statuses = ['pending', 'scheduled', 'collected', 'washing', 'drying', 'ready', 'delivered', 'completed'];

        foreach ($students as $index => $student) {
            LaundryRequest::query()->create([
                'tenant_id' => $student->tenant_id,
                'hostel_id' => $student->hostel_id,
                'student_id' => $student->id,
                'laundry_cycle_id' => $cycle->id,
                'service_type' => fake()->randomElement(['wash_only', 'wash_and_iron', 'dry_clean']),
                'bag_count' => fake()->numberBetween(1, 3),
                'weight_kg' => fake()->randomFloat(2, 2, 6),
                'status' => $statuses[$index] ?? 'pending',
                'requested_at' => now()->subHours(rand(2, 6)),
                'ready_at' => $index >= 4 ? now()->subHour() : null,
                'completed_at' => $index >= 5 ? now() : null,
                'metadata' => [
                    'notes' => fake()->optional()->sentence(),
                ],
            ]);
        }
    }

    protected function createSports(): void
    {
        if (! Schema::hasColumn('sports_events', 'sport')) {
            $this->command->warn('⚠️  Skipping sports demo data until sports_events schema is upgraded');
            return;
        }

        $event = SportsEvent::query()->create([
            'tenant_id' => $this->tenant->id,
            'campus_id' => $this->campus->id,
            'hostel_id' => null,
            'sport' => 'Basketball',
            'name' => 'Basketball Tournament',
            'description' => 'Inter-hostel friendly tournament',
            'scheduled_at' => now()->addDays(3)->setTime(17, 0),
            'end_time' => now()->addDays(3)->setTime(20, 0),
            'venue' => 'Sports Complex',
            'status' => 'scheduled',
            'capacity' => 20,
            'registration_deadline' => now()->addDays(2)->setTime(22, 0),
        ]);

        $students = Student::where('tenant_id', $this->tenant->id)->take(10)->get();
        foreach ($students as $index => $student) {
            SportsEnrollment::query()->create([
                'tenant_id' => $this->tenant->id,
                'student_id' => $student->id,
                'sports_event_id' => $event->id,
                'status' => $index < 7 ? 'registered' : 'waitlisted',
                'enrolled_at' => now()->subDays(rand(0, 2)),
            ]);
        }

        // Equipment loans removed from project
    }

    protected function createNotices(): void
    {
        $today = now();

        $notices = [
            [
                'title' => 'Welcome to the new semester',
                'body' => 'Please attend the orientation session at 5 PM in the auditorium.',
                'audience' => 'all_students',
                'channels' => ['push', 'email'],
                'attachment_url' => null,
            ],
            [
                'title' => 'Housekeeping audit',
                'body' => 'Rooms will be inspected tomorrow. Keep belongings organized.',
                'audience' => 'hostel_students',
                'channels' => ['push'],
                'attachment_url' => null,
            ],
            [
                'title' => 'Staff meeting',
                'body' => 'All staff requested to join the weekly sync at 10 AM.',
                'audience' => 'staff_only',
                'channels' => ['push', 'email'],
                'attachment_url' => null,
            ],
            [
                'title' => 'Sports tryouts',
                'body' => 'Register for basketball tryouts using the sports module.',
                'audience' => 'all_students',
                'channels' => ['push'],
                'attachment_url' => null,
            ],
            [
                'title' => 'Laundry maintenance window',
                'body' => 'Laundry services will be unavailable 2-4 PM for maintenance.',
                'audience' => 'all_students',
                'channels' => ['push'],
                'attachment_url' => null,
            ],
            [
                'title' => 'Emergency drill',
                'body' => 'Participate in the emergency evacuation drill on Friday.',
                'audience' => 'all_students',
                'channels' => ['push', 'sms'],
                'attachment_url' => 'https://cdn.mapservices.in/demo/notices/welcome-pack.pdf',
            ],
        ];

        foreach ($notices as $noticeData) {
            Notice::query()->create([
                'tenant_id' => $this->tenant->id,
                'campus_id' => $this->campus->id,
                'hostel_id' => null,
                'title' => $noticeData['title'],
                'body' => $noticeData['body'],
                'status' => 'published',
                'audience' => $noticeData['audience'],
                // Encode to JSON to avoid array-to-string errors on databases without casts
                'channels' => json_encode($noticeData['channels']),
                'publish_at' => $today->copy()->subHours(2),
                'published_at' => $today->copy()->subHours(2),
                'expires_at' => $today->copy()->addDays(7),
                'created_by_user_id' => $this->staff['CampusManager']->id,
                'attachment_url' => $noticeData['attachment_url'],
            ]);
        }
    }

    protected function createTickets(): void
    {
        $categories = ['housekeeping', 'maintenance', 'security', 'laundry', 'other'];
        $statuses = ['open', 'in_progress', 'on_hold', 'resolved', 'closed'];

        $tickets = collect();

        for ($i = 0; $i < 20; $i++) {
            $tickets->push(
                Ticket::query()->create([
                    'tenant_id' => $this->tenant->id,
                    'hostel_id' => fake()->randomElement([$this->hostelMale->id, $this->hostelFemale->id]),
                    'location' => fake()->randomElement(['Bathroom', 'Room 102', 'Mess Hall']),
                    'category' => fake()->randomElement($categories),
                    'priority' => fake()->randomElement(['low', 'medium', 'high']),
                    'status' => fake()->randomElement($statuses),
                    'reporter_student_id' => null,
                    'reporter_user_id' => $this->staff['CampusManager']->id,
                    'assignee_user_id' => fake()->randomElement([
                        $this->staff['HKSupervisor']->id,
                        $this->staff['RMSupervisor']->id,
                    ]),
                    'created_by_user_id' => $this->staff['CampusManager']->id,
                    'title' => 'Demo ticket #'.($i + 1),
                    'description' => fake()->sentence(12),
                    'due_date' => now()->addDays(2),
                    'sla_due_at' => now()->addDays(1),
                    'photos' => [],
                ])
            );
        }

        $commentTarget = (int) max(5, round($tickets->count() * 0.25));
        $commentTickets = $tickets->shuffle()->take($commentTarget);

        foreach ($commentTickets as $ticket) {
            TicketComment::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => fake()->randomElement([
                    $this->staff['HKSupervisor']->id,
                    $this->staff['RMSupervisor']->id,
                ]),
                'body' => fake()->sentence(),
                'attachments' => null,
            ]);
        }
    }

    /**
     * P1 Mobile UX Demo Data
     */
    protected function createP1StudentOutPasses(): void
    {
        $this->command->info('📱 Creating P1 Student out-passes...');

        // Get first 4 passworded students for P1 testing
        $passwordedStudents = collect($this->students)->take(4);

        foreach ($passwordedStudents as $student) {
            OutPass::query()->create([
                'tenant_id' => $student->tenant_id,
                'student_id' => $student->id,
                'hostel_id' => $student->hostel_id,
                'reason' => fake()->randomElement(['normal', 'leave', 'sick']),
                'overnight' => fake()->boolean(20),
                'status' => fake()->randomElement(['pending', 'approved']),
                'requested_at' => now('Asia/Kolkata'),
                'requested_for' => now('Asia/Kolkata')->toDateString(),
                'valid_until' => now('Asia/Kolkata')->addHours(8),
                'note' => fake()->optional(0.3)->sentence(),
                'idempotency_key' => (string) Str::uuid(),
                'decision_by' => $this->staff['Rector']->id,
            ]);
        }
    }

    protected function createP1AttendanceSession(): void
    {
        $this->command->info('📱 Creating P1 attendance session...');

        // Create an open attendance session for today
        $sessionDate = now('Asia/Kolkata');

        $session = AttendanceSession::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostelMale->id,
            'session_date' => $sessionDate->toDateString(),
            'session_time' => $sessionDate->format('H:i:s'),
            'name' => 'P1 Mobile UX Test Session',
            'kind' => 'night_check',
            'scheduled_at' => $sessionDate,
            'status' => 'open',
            'metadata' => [
                'open_at' => $sessionDate->copy()->subHour()->toISOString(),
                'close_at' => $sessionDate->copy()->addHours(2)->toISOString(),
                'session_date' => $sessionDate->toDateString(),
            ],
        ]);

        // Get 2 rooms for partial marking
        $rooms = Room::where('hostel_id', $this->hostelMale->id)->take(2)->get();

        foreach ($rooms as $room) {
            $beds = $room->beds()->where('status', 'occupied')->get();

            foreach ($beds as $bed) {
                if ($bed->student) {
                    // Mark some students as present, some as absent
                    $status = fake()->randomElement(['present', 'absent']);

                    AttendanceMark::query()->create([
                        'tenant_id' => $this->tenant->id,
                        'hostel_id' => $this->hostelMale->id,
                        'attendance_session_id' => $session->id,
                        'attendance_date' => $sessionDate->toDateString(),
                        'student_id' => $bed->student->id,
                        'room_id' => $room->id,
                        'status' => $status,
                        'marked_at' => $sessionDate,
                        'marked_by' => $this->staff['Warden']->id,
                    ]);
                }
            }
        }
    }

    protected function createP1SupervisorTickets(): void
    {
        $this->command->info('📱 Creating P1 supervisor tickets...');

        // Create 6 tickets with mix of statuses and assignments
        for ($i = 0; $i < 6; $i++) {
            $status = fake()->randomElement(['open', 'in_progress']);
            $assignedTo = fake()->optional(0.6)->randomElement([
                $this->staff['HKSupervisor']->id,
                $this->staff['RMSupervisor']->id,
            ]);

            $ticket = Ticket::query()->create([
                'tenant_id' => $this->tenant->id,
                'hostel_id' => $this->hostelMale->id,
                'location' => 'Hostel '.$this->hostelMale->code,
                'title' => 'P1 Mobile Test Ticket #'.($i + 1),
                'description' => 'This is a test ticket created for P1 mobile UX testing.',
                'priority' => fake()->randomElement(['low', 'medium', 'high']),
                'status' => $status,
                'category' => fake()->randomElement(['housekeeping', 'maintenance', 'security', 'laundry', 'other']),
                'assignee_user_id' => $assignedTo,
                'created_by_user_id' => $this->staff['CampusManager']->id,
                'reporter_user_id' => $this->staff['CampusManager']->id,
                'due_date' => now()->addDay(),
                'sla_due_at' => now()->addHours(8),
                'photos' => [],
            ]);

            if (fake()->boolean(40)) {
                TicketComment::query()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $assignedTo ?? $this->staff['HKSupervisor']->id,
                    'body' => 'P1 test comment for mobile UX validation.',
                    'attachments' => null,
                ]);
            }
        }
    }
}
