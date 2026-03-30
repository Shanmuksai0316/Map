<?php

namespace Database\Seeders;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Domain\Tickets\Models\Ticket;
use App\Enums\LaundryRequestStatus;
use App\Enums\LaundryServiceType;
use App\Enums\OutPassStatus;
use App\Enums\OutPassType;
use App\Enums\TenantStatus;
use App\Models\Campus;
use App\Models\GateEntry;
use App\Models\Hostel;
use App\Models\Incident;
use App\Models\LaundryRequest;
use App\Models\Room;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Domain\OutPass\OutPass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Lightweight baseline seeder for test suite
 * 
 * Creates minimal required data:
 * - 1 tenant (ACTIVE) with core feature flags enabled
 * - 1 campus
 * - 2 hostels (male/female) with rooms & beds
 * - Core staff members with roles and assignments
 * - 4 students (two per hostel) with allocations
 * - Baseline operational data (outpasses + open attendance session)
 */
class TestingBaselineSeeder extends Seeder
{
    protected ?Tenant $tenant = null;
    protected ?Campus $campus = null;
    protected ?Hostel $hostel = null;
    protected ?Hostel $secondHostel = null;
    protected array $staff = [];
    protected array $students = [];

    public function run(): void
    {
        $this->ensureRolesExistForSanctum();
        $this->createTenant();
        $this->createCampus();
        $this->createHostels();
        $this->createRoomsAndBeds();
        $this->createStaff();
        $this->createStaffAssignments();
        $this->createStudents();
        $this->createOperationalFixtures();
        $this->seedQaRegressionFixtures();
        $this->seedKpiFixtures();

        // Store tenant ID for TestCase to use
        app()->instance('testing.default_tenant_id', $this->tenant->id);

        // Mark tenant active after structure/data is in place
        $this->tenant->update(['status' => TenantStatus::ACTIVE]);
    }

    protected function ensureRolesExistForSanctum(): void
    {
        // Roles are created with 'web' guard by RolesAndPermissionsSeeder.
        // Ensure they exist for the web guard explicitly for test fixtures.
        $roles = [
            'Super Admin',
            'Campus Manager',
            'Rector',
            'College Management',
            'Warden',
            'Guard',
            'HK Supervisor',
            'RM Supervisor',
            'Laundry Manager',
            'Sports Manager',
            'Student',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }
    }

    protected function createTenant(): void
    {
        $this->tenant = Tenant::firstOrCreate(
            ['code' => 'MAP-TEST'],
            [
                'name' => 'Test College',
                'status' => TenantStatus::PROVISIONING,
                'addon_security' => true,
                'addon_sports' => true,
                'addon_laundry' => true,
                'settings' => [
                    'payments_s3' => false,
                    'sms_events' => [],
                    'checklists_module' => true,
                    'attendance_module' => true,
                    'sports_module' => true,
                    'laundry_module' => true,
                    'gate_device_enforcement' => false,
                ],
            ]
        );
    }

    protected function createCampus(): void
    {
        // Ensure campuses table exists - wait a bit if needed for migrations to complete
        $maxAttempts = 10;
        $attempt = 0;
        while (!\Illuminate\Support\Facades\Schema::hasTable('campuses') && $attempt < $maxAttempts) {
            $attempt++;
            usleep(100000); // Wait 100ms
            // Clear schema cache
            try {
                \Illuminate\Support\Facades\DB::connection()->getDoctrineSchemaManager()->clearCache();
            } catch (\Exception $e) {
                // Ignore cache errors
            }
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('campuses')) {
            throw new \RuntimeException('Campuses table does not exist after ' . $maxAttempts . ' attempts. Migrations may not have completed.');
        }

        $this->campus = Campus::firstOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'code' => 'MAIN',
            ],
            [
                'name' => 'Main Campus',
                'address' => [
                    'line1' => '123 Test Street',
                    'city' => 'Test City',
                    'state' => 'TS',
                    'pincode' => '123456',
                ],
            ]
        );
    }

    protected function createHostels(): void
    {
        $this->hostel = Hostel::firstOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'code' => 'H1',
            ],
            [
                'campus_id' => $this->campus->id,
                'name' => 'Hostel 1',
                'gender_mode' => 'Male',
                'curfew_time' => '22:30:00',
                'overnight_enabled' => false,
                'visiting_start' => '16:00:00',
                'visiting_end' => '19:00:00',
                'settings' => [],
            ]
        );

        $this->secondHostel = Hostel::firstOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'code' => 'H2',
            ],
            [
                'campus_id' => $this->campus->id,
                'name' => 'Hostel 2',
                'gender_mode' => 'Female',
                'curfew_time' => '22:15:00',
                'overnight_enabled' => true,
                'visiting_start' => '15:00:00',
                'visiting_end' => '20:00:00',
                'settings' => [],
            ]
        );
    }

    protected function createRoomsAndBeds(): void
    {
        $hostelConfigs = [
            ['hostel' => $this->hostel, 'floors' => ['1', '2'], 'rooms_per_floor' => 3],
            ['hostel' => $this->secondHostel, 'floors' => ['1'], 'rooms_per_floor' => 2],
        ];

        foreach ($hostelConfigs as $config) {
            $hostel = $config['hostel'];
            if (!$hostel) {
                continue;
            }

            $floors = $config['floors'];
            $roomsPerFloor = $config['rooms_per_floor'];
            $bedsPerRoom = 2;

            foreach ($floors as $floor) {
                for ($roomNum = 1; $roomNum <= $roomsPerFloor; $roomNum++) {
                    $room = Room::firstOrCreate(
                        [
                            'tenant_id' => $this->tenant->id,
                            'hostel_id' => $hostel->id,
                            'number' => $floor . str_pad((string) $roomNum, 2, '0', STR_PAD_LEFT),
                        ],
                        [
                            'campus_id' => $this->campus->id,
                            'block_code' => 'A',
                            'floor_code' => $floor,
                            'capacity' => $bedsPerRoom,
                            'is_active' => true,
                        ]
                    );

                    for ($bedNum = 0; $bedNum < $bedsPerRoom; $bedNum++) {
                        RoomBed::firstOrCreate(
                            [
                                'tenant_id' => $this->tenant->id,
                                'room_id' => $room->id,
                                'code' => chr(65 + $bedNum), // A, B
                            ],
                            [
                                'hostel_id' => $hostel->id,
                                'status' => 'available',
                            ]
                        );
                    }
                }
            }
        }
    }

    protected function createStaff(): void
    {
        $staffData = [
            [
                'name' => 'Test Campus Manager',
                'phone' => '+919900000001',
                'kind' => 'CampusManager',
                'role' => 'Campus Manager',
                'email' => null,
            ],
            [
                'name' => 'Test Rector',
                'phone' => '+919900000002',
                'kind' => 'Rector',
                'role' => 'Rector',
                'email' => null,
            ],
            [
                'name' => 'Test Warden',
                'phone' => '+919900000003',
                'kind' => 'Warden',
                'role' => 'Warden',
                'email' => null,
            ],
            [
                'name' => 'Test Guard',
                'phone' => '+919900000004',
                'kind' => 'Guard',
                'role' => 'Guard',
                'email' => null,
            ],
            [
                'name' => 'Test HK Supervisor',
                'phone' => '+919900000005',
                'kind' => 'HKSupervisor',
                'role' => 'HK Supervisor',
                'email' => null,
            ],
            [
                'name' => 'Test RM Supervisor',
                'phone' => '+919900000006',
                'kind' => 'RMSupervisor',
                'role' => 'RM Supervisor',
                'email' => null,
            ],
            [
                'name' => 'Test Laundry Manager',
                'phone' => '+919900000007',
                'kind' => 'LaundryManager',
                'role' => 'Laundry Manager',
                'email' => null,
            ],
        ];

        foreach ($staffData as $data) {
            $user = User::firstOrCreate(
                ['phone' => $data['phone']],
                [
                    'tenant_id' => $this->tenant->id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'kind' => strtolower($data['kind']),
                    'password' => Hash::make('test123'), // For web session, not login
                ]
            );

            // Ensure role is assigned (web guard)
            $role = Role::findByName($data['role'], 'web');
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }

            $this->staff[$data['kind']] = $user;
        }
    }

    protected function createStaffAssignments(): void
    {
        // Campus Manager: tenant-wide (no staff_assignments record needed)
        // Other staff: hostel-scoped (require staff_assignments records)
        $hostelScopedRoles = ['Rector', 'Warden', 'Guard', 'HKSupervisor', 'RMSupervisor'];
        foreach ($hostelScopedRoles as $roleKind) {
            if (!isset($this->staff[$roleKind])) {
                continue;
            }

            DB::table('staff_assignments')->updateOrInsert(
                [
                    'tenant_id' => $this->tenant->id,
                    'user_id' => $this->staff[$roleKind]->id,
                    'hostel_id' => $this->hostel->id,
                ],
                [
                    'assigned_at' => now(),
                    'revoked_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    protected function createStudents(): void
    {
        $hostels = array_filter([$this->hostel, $this->secondHostel]);
        $studentIndex = 1;

        $availableBeds = [];
        foreach ($hostels as $hostel) {
            $availableBeds[$hostel->id] = RoomBed::where('tenant_id', $this->tenant->id)
                ->where('hostel_id', $hostel->id)
                ->pluck('id')
                ->toArray();
        }

        foreach ($hostels as $hostel) {
            for ($i = 0; $i < 2; $i++) {
                $user = User::firstOrCreate(
                    ['phone' => '+919900001' . str_pad((string) $studentIndex, 3, '0', STR_PAD_LEFT)],
                    [
                        'tenant_id' => $this->tenant->id,
                        'name' => "Test Student {$studentIndex}",
                        'email' => null,
                        'kind' => 'student',
                        'password' => Hash::make('test123'),
                    ]
                );

                $studentRole = Role::findByName('Student', 'web');
                if (!$user->hasRole($studentRole)) {
                    $user->assignRole($studentRole);
                }

                $student = Student::firstOrCreate(
                    [
                        'tenant_id' => $this->tenant->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'hostel_id' => $hostel->id,
                        'student_uid' => 'STD-TEST-' . str_pad((string) $studentIndex, 3, '0', STR_PAD_LEFT),
                        'map_student_id' => 'MAP-STD-' . str_pad((string) $studentIndex, 3, '0', STR_PAD_LEFT),
                        'roll_no' => 'RN' . str_pad((string) $studentIndex, 3, '0', STR_PAD_LEFT),
                        'program' => 'B.Tech',
                        'year_of_study' => 2,
                        'admission_year' => 2023,
                        'guardian' => [
                            'name' => "Guardian {$studentIndex}",
                            'phone' => '+919900002' . str_pad((string) $studentIndex, 3, '0', STR_PAD_LEFT),
                        ],
                        'medical_notes' => [],
                        'correspondence_address' => [
                            'line1' => "Student {$studentIndex} Address",
                            'city' => 'Test City',
                        ],
                    ]
                );

                $this->students[] = $student;

                $roomBedId = array_shift($availableBeds[$hostel->id]);

                if ($roomBedId) {
                    RoomAllocation::firstOrCreate(
                        [
                            'tenant_id' => $this->tenant->id,
                            'student_id' => $student->id,
                            'is_active' => true,
                        ],
                        [
                            'room_bed_id' => $roomBedId,
                            'hostel_id' => $hostel->id,
                            'effective_from' => now()->subWeek(),
                            'note' => 'Baseline allocation',
                        ]
                    );
                }

                $studentIndex++;
            }
        }
    }

    protected function createOperationalFixtures(): void
    {
        if (empty($this->students)) {
            return;
        }

        $primaryStudent = $this->students[0];
        $secondaryStudent = $this->students[1] ?? $primaryStudent;

        OutPass::firstOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'student_id' => $primaryStudent->id,
                'hostel_id' => $primaryStudent->hostel_id,
                'status' => OutPassStatus::PENDING,
            ],
            [
                'reason' => OutPassType::NORMAL,
                'overnight' => false,
                'requested_at' => now()->subHours(4),
                'valid_until' => now()->addDay(),
                'note' => 'Baseline pending out-pass',
                'idempotency_key' => (string) Str::uuid(),
            ]
        );

        OutPass::firstOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'student_id' => $secondaryStudent->id,
                'hostel_id' => $secondaryStudent->hostel_id,
                'status' => OutPassStatus::APPROVED,
            ],
            [
                'reason' => OutPassType::LEAVE,
                'overnight' => true,
                'requested_at' => now()->subDay(),
                'decided_at' => now()->subHours(2),
                'valid_until' => now()->addHours(6),
                'note' => 'Approved for medical checkup',
                'idempotency_key' => (string) Str::uuid(),
                'decision_by' => $this->staff['Rector']->id ?? null,
            ]
        );

        $nowKolkata = Carbon::now('Asia/Kolkata');
        $session = AttendanceSession::firstOrNew([
            'hostel_id' => $this->hostel->id,
            'session_date' => $nowKolkata->toDateString(),
        ]);
        $session->tenant_id = $this->tenant->id;
        $session->status = 'in_progress';
        $session->session_time = $nowKolkata->format('H:i:s');
        $session->started_at = $nowKolkata->copy()->subMinutes(15);
        $session->started_by = $this->staff['Warden']->id ?? null;
        $session->save();

        // Guest visit data omitted for baseline seed due to legacy table schema differences.
    }

    /**
     * Seed minimal KPI data to satisfy dashboard/kpi tests.
     */
    protected function seedKpiFixtures(): void
    {
        $template = ChecklistTemplate::firstOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'role' => 'Warden',
                'title' => 'Baseline Warden Checklist',
            ],
            [
                'tasks' => [
                    ['code' => 't1', 'label' => 'Inspect rooms', 'state' => 'Pending'],
                ],
                'active' => true,
                'created_by_user_id' => $this->staff['Campus Manager']->id ?? null,
            ]
        );

        // Checklist instance with due_at/completed_at
        ChecklistInstance::create([
            'tenant_id' => $this->tenant->id,
            'template_id' => $template->id,
            'date' => now()->toDateString(),
            'shift' => 'Morning',
            'role' => 'Warden',
            'assignee_user_id' => $this->staff['Warden']->id ?? null,
            'status' => 'Submitted',
            'review_status' => 'Approved',
            'total_tasks' => 1,
            'completed_tasks' => 1,
            'submitted_at' => now()->subHours(1),
            'manager_user_id' => $this->staff['Campus Manager']->id ?? null,
            'reviewed_at' => now(),
            'due_at' => now()->addHour(),
            'completed_at' => now()->addMinutes(30),
        ]);

        // Gate entry late return sample
        GateEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel->id,
            'student_id' => $this->students[0]->id ?? null,
            'direction' => 'IN',
            'occurred_at' => now()->subMinutes(10),
            'late_minutes' => 15,
        ]);
    }

    /**
     * Deterministic fixtures used by QA regression flows.
     */
    protected function seedQaRegressionFixtures(): void
    {
        $createdBy = $this->staff['CampusManager']->id ?? null;
        $hostelId = $this->hostel?->id;
        $campusId = $this->campus?->id;

        $checklistTemplates = [
            [
                'role' => 'Guard',
                'title' => 'QA Guard Daily Checklist',
                'tasks' => [
                    ['code' => 'GUARD_GATE', 'label' => 'Gate register updated', 'require_photo' => false, 'require_comment' => false],
                    ['code' => 'GUARD_ROUND', 'label' => 'Patrol evidence', 'require_photo' => true, 'require_comment' => true],
                ],
            ],
            [
                'role' => 'HKSupervisor',
                'title' => 'QA HK Daily Checklist',
                'tasks' => [
                    ['code' => 'HK_ROOM', 'label' => 'Room hygiene check', 'require_photo' => true, 'require_comment' => false],
                    ['code' => 'HK_SUPPLY', 'label' => 'Supply remarks', 'require_photo' => false, 'require_comment' => true],
                ],
            ],
            [
                'role' => 'RMSupervisor',
                'title' => 'QA RM Daily Checklist',
                'tasks' => [
                    ['code' => 'RM_REPAIR', 'label' => 'Repair verification', 'require_photo' => true, 'require_comment' => true],
                    ['code' => 'RM_PANEL', 'label' => 'Panel safety check', 'require_photo' => false, 'require_comment' => false],
                ],
            ],
        ];

        foreach ($checklistTemplates as $template) {
            ChecklistTemplate::updateOrCreate(
                [
                    'tenant_id' => $this->tenant->id,
                    'role' => $template['role'],
                    'title' => $template['title'],
                ],
                [
                    'tasks' => $template['tasks'],
                    'active' => true,
                    'created_by_user_id' => $createdBy,
                    'updated_at' => now(),
                ]
            );
        }

        // Ensure RM/HK request transitions have deterministic records.
        $reporter = $this->students[0] ?? null;
        if ($reporter && $hostelId) {
            $hasSource = \Illuminate\Support\Facades\Schema::hasColumn('tickets', 'source');

            $hasSource = \Illuminate\Support\Facades\Schema::hasColumn('tickets', 'source');
            $hostelName = $this->hostel?->name ?? 'QA Hostel';
            $createdBy = $this->staff['CampusManager']->id ?? null;

            Ticket::updateOrCreate(
                [
                    'tenant_id' => $this->tenant->id,
                    'hostel_id' => $hostelId,
                    'title' => 'QA RM Open Ticket',
                ],
                array_filter([
                    'category' => 'repair_maintenance',
                    'description' => 'QA seeded open ticket for RM transition checks',
                    'priority' => 'medium',
                    'status' => 'open',
                    'location' => $hostelName,
                    'created_by' => $createdBy,
                    'source' => $hasSource ? 'mobile' : null,
                    'reporter_student_id' => $reporter->id,
                ], fn($v) => $v !== null)
            );

            Ticket::updateOrCreate(
                [
                    'tenant_id' => $this->tenant->id,
                    'hostel_id' => $hostelId,
                    'title' => 'QA HK In Progress Ticket',
                ],
                array_filter([
                    'category' => 'housekeeping',
                    'description' => 'QA seeded in-progress ticket for HK transition checks',
                    'priority' => 'medium',
                    'status' => 'in_progress',
                    'location' => $hostelName,
                    'created_by' => $createdBy,
                    'source' => $hasSource ? 'mobile' : null,
                    'reporter_student_id' => $reporter->id,
                ], fn($v) => $v !== null)
            );
        }

        // Laundry lifecycle fixtures for filters/actions.
        $student = $this->students[0] ?? null;
        if ($student && $hostelId && $campusId) {
            $laundryManagerId = $this->staff['LaundryManager']->id ?? null;
            $statuses = [
                LaundryRequestStatus::PENDING,
                LaundryRequestStatus::WASHING,
                LaundryRequestStatus::DRYING,
                LaundryRequestStatus::READY,
                LaundryRequestStatus::COMPLETED,
            ];

            foreach ($statuses as $status) {
                LaundryRequest::updateOrCreate(
                    [
                        'tenant_id' => $this->tenant->id,
                        'student_id' => $student->id,
                        'status' => $status,
                    ],
                    [
                        'campus_id' => $campusId,
                        'hostel_id' => $hostelId,
                        'service_type' => LaundryServiceType::WASH_ONLY,
                        'bag_count' => 1,
                        'weight_kg' => 2.5,
                        'requested_at' => now()->subHours(8),
                        'initiated_by_user_id' => $laundryManagerId,
                        'pickup_code' => $status === LaundryRequestStatus::READY ? '1234' : null,
                        'ready_at' => $status === LaundryRequestStatus::READY ? now()->subHour() : null,
                        'completed_at' => $status === LaundryRequestStatus::COMPLETED ? now()->subMinutes(30) : null,
                    ]
                );
            }
        }

        // Guard movement/history fixture.
        if (($this->students[0] ?? null) && $hostelId) {
            GateEntry::firstOrCreate(
                [
                    'tenant_id' => $this->tenant->id,
                    'hostel_id' => $hostelId,
                    'student_id' => $this->students[0]->id,
                    'direction' => 'OUT',
                    'occurred_at' => now()->subHours(2),
                ],
                [
                    'late_minutes' => 0,
                ]
            );
        }

        // Warden emergency fixture.
        if (($this->students[0] ?? null) && $hostelId) {
            Incident::updateOrCreate(
                [
                    'tenant_id' => $this->tenant->id,
                    'hostel_id' => $hostelId,
                    'student_id' => $this->students[0]->id,
                    'type' => Incident::TYPE_SECURITY,
                    'note' => 'QA seeded emergency alert for warden E2E flow',
                ],
                [
                    'status' => 'Open',
                    'opened_by' => $this->staff['Warden']->id ?? null,
                    'opened_at' => now()->subMinutes(20),
                ]
            );
        }
    }
}

