<?php

namespace App\Console\Commands;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistItem;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Domain\GuestEntries\Models\GuestEntry;
use App\Domain\Leaves\Models\Leave;
use App\Domain\Tickets\Models\Ticket;
use App\Enums\LaundryRequestStatus;
use App\Enums\LaundryServiceType;
use App\Enums\OutPassStatus;
use App\Enums\OutPassType;
use App\Models\Campus;
use App\Models\Hostel;
use App\Models\LaundryRequest;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Parcel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Stancl\Tenancy\Facades\Tenancy;

class SeedStxavDemoData extends Command
{
    protected $signature = 'seed:stxav-demo
        {--count=5 : Number of sample records per type}
        {--tenant=STXAV : Tenant code to seed}';

    protected $description = 'Seed Outpass, Leave, Guest Entry, Laundry, Tickets, Parcels, and Checklist data for staff flows.';

    public function handle(): int
    {
        $tenantCode = $this->option('tenant');
        $tenant = Tenant::where('code', $tenantCode)->first();

        if (! $tenant) {
            $this->error("Tenant {$tenantCode} not found.");
            return Command::FAILURE;
        }

        $count = max(1, (int) $this->option('count'));

        $this->info("Seeding {$count} sample records per type for {$tenant->name} ({$tenant->code})");

        Tenancy::initialize($tenant);

        try {
            $hostel = Hostel::where('tenant_id', $tenant->id)->first();
            $campus = Campus::where('tenant_id', $tenant->id)->first();
            $students = Student::where('tenant_id', $tenant->id)->take(max($count, 10))->get();

            if (! $hostel || ! $campus || $students->isEmpty()) {
                $this->warn('Hostel, campus or students not found. Run demos seeders first.');
                return Command::FAILURE;
            }

            $this->seedOutpasses($tenant, $hostel, $students, $count);
            $this->seedLeaves($tenant, $hostel, $students, $count);
            $this->seedGuestEntries($tenant, $hostel, $students, $count);

            try {
                $this->seedLaundryRequests($tenant, $hostel, $students, $count);
            } catch (\Throwable $e) {
                $this->warn('  Laundry seeding skipped: ' . $e->getMessage());
            }
            try {
                $this->seedTickets($tenant, $hostel, $students, $count);
            } catch (\Throwable $e) {
                $this->warn('  Tickets seeding skipped: ' . $e->getMessage());
            }
            try {
                $this->seedParcels($tenant, $hostel, $students, $count);
            } catch (\Throwable $e) {
                $this->warn('  Parcels seeding skipped: ' . $e->getMessage());
            }
            try {
                $this->seedChecklistTemplatesAndInstances($tenant);
            } catch (\Throwable $e) {
                $this->warn('  Checklists seeding skipped: ' . $e->getMessage());
            }

            $this->info('✅ Demo data seeded successfully.');
            return Command::SUCCESS;
        } finally {
            Tenancy::end();
        }
    }

    protected function seedOutpasses(Tenant $tenant, Hostel $hostel, $students, int $count): void
    {
        $this->info(' → Seeding Outpass requests...');

        $reasons = [OutPassType::NORMAL, OutPassType::LEAVE, OutPassType::SICK];

        for ($i = 0; $i < $count; $i++) {
            $student = $students[$i % $students->count()];
            $requestedAt = now()->subHours(random_int(1, 24));

            OutPass::create([
                'tenant_id' => $tenant->id,
                'student_id' => $student->id,
                'hostel_id' => $student->hostel_id ?: $hostel->id,
                'reason' => $reasons[$i % count($reasons)],
                'overnight' => (bool) random_int(0, 1),
                'status' => $i % 2 === 0 ? OutPassStatus::PENDING : OutPassStatus::APPROVED,
                'requested_at' => $requestedAt,
                'requested_for' => $requestedAt->toDateString(),
                'valid_until' => $requestedAt->copy()->addHours(8),
                'note' => 'Demo outpass #' . ($i + 1),
                'idempotency_key' => (string) Str::uuid(),
            ]);
        }
    }

    protected function seedLeaves(Tenant $tenant, Hostel $hostel, $students, int $count): void
    {
        $this->info(' → Seeding Leave requests...');

        $reasons = ['Family Emergency', 'Medical', 'Personal Work', 'Festival', 'Wedding'];

        for ($i = 0; $i < $count; $i++) {
            $student = $students[$i % $students->count()];
            $from = now()->addDays($i + 1);
            $to = $from->copy()->addDays(random_int(1, 5));

            Leave::create([
                'tenant_id' => $tenant->id,
                'student_id' => $student->id,
                'hostel_id' => $student->hostel_id ?: $hostel->id,
                'title' => 'Demo leave #' . ($i + 1),
                'description' => 'Seeded leave for QA flows.',
                'reason_for_leave' => $reasons[$i % count($reasons)],
                'from_date' => $from,
                'to_date' => $to,
                'emergency_contact' => $student->phone ?? null,
                'status' => $i % 3 === 0 ? 'approved' : ($i % 3 === 1 ? 'pending' : 'rejected'),
            ]);
        }
    }

    protected function seedGuestEntries(Tenant $tenant, Hostel $hostel, $students, int $count): void
    {
        $this->info(' → Seeding Guest Entry requests...');

        $purposes = ['Parents visit', 'Relatives', 'Friends', 'Official meeting', 'Festival'];

        for ($i = 0; $i < $count; $i++) {
            $student = $students[$i % $students->count()];
            $visitDate = now()->addDays($i % 3);

            GuestEntry::create([
                'tenant_id' => $tenant->id,
                'student_id' => $student->id,
                'hostel_id' => $student->hostel_id ?: $hostel->id,
                'title' => 'Guest visit #' . ($i + 1),
                'description' => 'Demo guest entry for QA.',
                'guests' => [
                    ['name' => 'Guest ' . ($i + 1), 'relation' => 'Family', 'phone' => '+91987654321' . ($i % 10)],
                ],
                'primary_contact_mobile' => '+91987654321' . ($i % 10),
                'visit_date' => $visitDate,
                'check_in_time' => '10:00',
                'check_out_time' => '18:00',
                'purpose_to_visit' => $purposes[$i % count($purposes)],
                'status' => $i % 2 === 0 ? 'pending' : 'approved',
                'submitted_at' => now()->subHours($i + 1),
                'idempotency_key' => (string) Str::uuid(),
            ]);
        }
    }

    protected function seedLaundryRequests(Tenant $tenant, Hostel $hostel, $students, int $count): void
    {
        $this->info(' → Seeding Laundry requests...');

        $laundryManager = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Laundry Manager'))
            ->first();

        $statuses = [
            LaundryRequestStatus::PENDING,
            LaundryRequestStatus::WASHING,
            LaundryRequestStatus::DRYING,
            LaundryRequestStatus::READY,
            LaundryRequestStatus::COMPLETED,
        ];

        for ($i = 0; $i < $count; $i++) {
            $student = $students[$i % $students->count()];
            $status = $statuses[$i % count($statuses)];

            LaundryRequest::create([
                'hostel_id' => $student->hostel_id ?: $hostel->id,
                'student_id' => $student->id,
                'initiated_by_user_id' => $laundryManager?->id,
                'service_type' => LaundryServiceType::WASH_ONLY,
                'status' => $status,
                'bag_count' => 1,
                'weight_kg' => 2.5,
                'requested_at' => now()->subHours(8),
                'ready_at' => in_array($status, [LaundryRequestStatus::READY, LaundryRequestStatus::COMPLETED], true)
                    ? now()->subHour()
                    : null,
                'completed_at' => $status === LaundryRequestStatus::COMPLETED ? now()->subMinutes(30) : null,
                'notes' => 'Demo laundry #' . ($i + 1),
            ]);
        }
    }

    protected function seedTickets(Tenant $tenant, Hostel $hostel, $students, int $count): void
    {
        $this->info(' → Seeding Tickets (HK / RM / Security / Laundry)...');

        $categories = ['housekeeping', 'repair_maintenance', 'security', 'laundry', 'other'];
        $priorities = ['low', 'medium', 'high'];
        $statuses = ['open', 'in_progress', 'resolved', 'closed'];

        $staffByRole = [
            'housekeeping' => User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'HK Supervisor'))->first(),
            'repair_maintenance' => User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'RM Supervisor'))->first(),
            'security' => User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'Guard'))->first(),
            'laundry' => User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'Laundry Manager'))->first(),
            'other' => User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'Campus Manager'))->first(),
        ];

        for ($i = 0; $i < $count; $i++) {
            $category = $categories[$i % count($categories)];
            $priority = $priorities[$i % count($priorities)];
            $status = $statuses[$i % count($statuses)];
            $student = $students[$i % $students->count()];
            $assignee = $staffByRole[$category] ?? null;

            Ticket::create([
                'tenant_id' => $tenant->id,
                'hostel_id' => $student->hostel_id ?: $hostel->id,
                'title' => 'Demo ticket #' . ($i + 1) . " ({$category})",
                'description' => 'Seeded for QA flows.',
                'category' => $category,
                'priority' => $priority,
                'status' => $status,
                'location' => $hostel->name,
                'reporter_student_id' => $student->id,
                'created_by_user_id' => $assignee?->id,
                'assignee_user_id' => $assignee?->id,
            ]);
        }
    }

    protected function seedParcels(Tenant $tenant, Hostel $hostel, $students, int $count): void
    {
        $this->info(' → Seeding Parcels (Warden flow)...');

        $warden = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'Warden'))->first();
        if (! $warden) {
            $this->warn('    No Warden found, skipping parcels.');
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $student = $students[$i % $students->count()];
            $code = sprintf('%04d', random_int(1000, 9999));
            $status = $i % 2 === 0 ? Parcel::STATUS_INFORMED : Parcel::STATUS_RECEIVED;

            Parcel::create([
                // parcels.tenant_id is bigint on the server; use placeholder numeric value for demo data
                'tenant_id' => 0,
                'hostel_id' => $student->hostel_id ?: $hostel->id,
                'student_id' => $student->id,
                'received_by_user_id' => $warden->id,
                'status' => $status,
                'code' => $code,
                'room_number' => 'R' . ($i % 20 + 101),
                'notes' => 'Demo parcel #' . ($i + 1),
                'informed_at' => now()->subHours($i + 1),
                'received_at' => $status === Parcel::STATUS_RECEIVED ? now()->subMinutes(30) : null,
            ]);
        }
    }

    protected function seedChecklistTemplatesAndInstances(Tenant $tenant): void
    {
        $this->info(' → Seeding Checklist templates and instances for all roles...');

        $roles = ['Guard', 'Warden', 'HK Supervisor', 'RM Supervisor'];
        $templatesConfig = [
            'Guard' => [
                ['code' => 'gate_log', 'label' => 'Update gate entry/exit log'],
                ['code' => 'fire_exit', 'label' => 'Check fire exits are clear'],
            ],
            'Warden' => [
                ['code' => 'hostel_round', 'label' => 'Complete hostel round'],
                ['code' => 'incident_log', 'label' => 'Review incident log'],
            ],
            'HK Supervisor' => [
                ['code' => 'common_area', 'label' => 'Inspect common areas'],
                ['code' => 'restrooms', 'label' => 'Inspect restrooms'],
            ],
            'RM Supervisor' => [
                ['code' => 'maintenance_queue', 'label' => 'Review maintenance queue'],
                ['code' => 'safety_checks', 'label' => 'Complete safety checks'],
            ],
        ];

        foreach ($roles as $role) {
            $staff = User::where('tenant_id', $tenant->id)
                ->whereHas('roles', fn ($q) => $q->where('name', $role))
                ->first();

            if (! $staff) {
                continue;
            }

            $tasks = $templatesConfig[$role] ?? $templatesConfig['Guard'];
            $template = ChecklistTemplate::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'role' => $role,
                    'title' => $role . ' Daily Checklist',
                ],
                [
                    'tasks' => collect($tasks)->map(fn ($t) => [
                        'code' => $t['code'],
                        'label' => $t['label'],
                        'require_photo' => false,
                        'require_comment' => false,
                    ])->toArray(),
                    'active' => true,
                    'created_by_user_id' => $staff->id,
                ]
            );

            // Create instances for today and last 3 days
            for ($day = 0; $day < 4; $day++) {
                $date = now()->subDays($day);
                $status = $day === 0 ? ChecklistInstance::STATUS_PENDING : (
                    $day === 1 ? ChecklistInstance::STATUS_SUBMITTED : ChecklistInstance::STATUS_APPROVED
                );

                $instance = ChecklistInstance::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'template_id' => $template->id,
                        'date' => $date,
                        'assignee_user_id' => $staff->id,
                    ],
                    [
                        'shift' => ['Morning', 'Evening', 'Night'][$day % 3],
                        'role' => $role,
                        'status' => $status,
                        'total_tasks' => count($tasks),
                        'completed_tasks' => $status !== ChecklistInstance::STATUS_PENDING ? count($tasks) : 0,
                        'submitted_at' => $status !== ChecklistInstance::STATUS_PENDING ? $date->copy()->addHours(8) : null,
                        'manager_user_id' => $status === ChecklistInstance::STATUS_APPROVED ? $staff->id : null,
                        'manager_note' => $status === ChecklistInstance::STATUS_APPROVED ? 'All tasks completed.' : null,
                        'reviewed_at' => $status === ChecklistInstance::STATUS_APPROVED ? $date->copy()->addHours(10) : null,
                    ]
                );

                // Create items if not exists
                if ($instance->items()->count() === 0) {
                    foreach ($tasks as $idx => $task) {
                        ChecklistItem::create([
                            'tenant_id' => $tenant->id,
                            'instance_id' => $instance->id,
                            'code' => $task['code'],
                            'label' => $task['label'],
                            'state' => $status !== ChecklistInstance::STATUS_PENDING ? 'Done' : 'Pending',
                            'completed_at' => $status !== ChecklistInstance::STATUS_PENDING ? $date->copy()->addHours(rand(1, 7)) : null,
                        ]);
                    }
                }
            }
        }
    }
}
