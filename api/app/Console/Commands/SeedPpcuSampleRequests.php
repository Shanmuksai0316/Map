<?php

namespace App\Console\Commands;

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
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Stancl\Tenancy\Facades\Tenancy;

class SeedPpcuSampleRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ppcu:seed-requests {--count=5 : Number of sample records per type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed sample Outpass, Leave, Laundry and Ticket requests for PPCU (MAP-PPCU) tenant to exercise staff flows.';

    public function handle(): int
    {
        $tenant = Tenant::where('code', 'MAP-PPCU')->first();
        if (! $tenant) {
            $this->error('Tenant MAP-PPCU not found.');
            return Command::FAILURE;
        }

        $count = (int) $this->option('count');
        if ($count <= 0) {
            $count = 5;
        }

        $this->info("Seeding {$count} sample records per request type for tenant {$tenant->name} ({$tenant->code})");

        // Run inside tenant context so automatic scoping (RLS) and connections are correct.
        Tenancy::initialize($tenant);

        try {
            $hostel = Hostel::where('tenant_id', $tenant->id)->first();
            $campus = Campus::where('tenant_id', $tenant->id)->first();
            $students = Student::where('tenant_id', $tenant->id)->take($count)->get();

            if (! $hostel || ! $campus || $students->isEmpty()) {
                $this->warn('Hostel, campus or students not found for MAP-PPCU. No records were created.');
                return Command::FAILURE;
            }

            $this->seedOutpasses($tenant, $hostel, $students, $count);
            $this->seedLeaves($tenant, $hostel, $students, $count);
            $this->seedLaundryRequests($tenant, $campus, $hostel, $students, $count);
            $this->seedTickets($tenant, $hostel, $students, $count);

            $this->info('✅ PPCU sample requests seeded successfully.');
            return Command::SUCCESS;
        } finally {
            Tenancy::end();
        }
    }

    protected function seedOutpasses(Tenant $tenant, Hostel $hostel, $students, int $count): void
    {
        $this->info(' → Seeding Out-pass requests for Rector / Warden / Guard flows...');

        $reasons = [
            OutPassType::NORMAL,
            OutPassType::EMERGENCY,
            OutPassType::MEDICAL,
        ];

        for ($i = 0; $i < $count; $i++) {
            /** @var \App\Models\Student $student */
            $student = $students[$i % $students->count()];

            $requestedAt = now()->subHours(random_int(1, 6));

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
                'note' => 'PPCU QA Outpass #' . ($i + 1),
                'idempotency_key' => (string) Str::uuid(),
            ]);
        }
    }

    protected function seedLeaves(Tenant $tenant, Hostel $hostel, $students, int $count): void
    {
        $this->info(' → Seeding Leave requests for Rector / Warden flows...');

        $reasons = [
            'Family Emergency',
            'Medical',
            'Personal Work',
            'Festival',
            'Wedding',
        ];

        for ($i = 0; $i < $count; $i++) {
            /** @var \App\Models\Student $student */
            $student = $students[$i % $students->count()];

            $from = now()->addDays($i + 1);
            $to = $from->copy()->addDays(random_int(1, 5));

            Leave::create([
                'tenant_id' => $tenant->id,
                'student_id' => $student->id,
                'hostel_id' => $student->hostel_id ?: $hostel->id,
                'title' => 'PPCU QA Leave #' . ($i + 1),
                'description' => 'Seeded leave request for PPCU QA flows.',
                'reason_for_leave' => $reasons[$i % count($reasons)],
                'from_date' => $from,
                'to_date' => $to,
                'emergency_contact' => $student->phone ?? null,
                'status' => $i % 3 === 0 ? 'approved' : ($i % 3 === 1 ? 'pending' : 'rejected'),
            ]);
        }
    }

    protected function seedLaundryRequests(Tenant $tenant, Campus $campus, Hostel $hostel, $students, int $count): void
    {
        $this->info(' → Seeding Laundry requests for Laundry Manager flows...');

        $laundryManager = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', function ($q) {
                $q->where('name', 'Laundry Manager');
            })
            ->first();

        $statuses = [
            LaundryRequestStatus::PENDING,
            LaundryRequestStatus::WASHING,
            LaundryRequestStatus::DRYING,
            LaundryRequestStatus::READY,
            LaundryRequestStatus::COMPLETED,
        ];

        for ($i = 0; $i < $count; $i++) {
            /** @var \App\Models\Student $student */
            $student = $students[$i % $students->count()];
            $status = $statuses[$i % count($statuses)];

            LaundryRequest::create([
                'campus_id' => $campus->id,
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
                'special_instructions' => 'PPCU QA laundry request #' . ($i + 1),
            ]);
        }
    }

    protected function seedTickets(Tenant $tenant, Hostel $hostel, $students, int $count): void
    {
        $this->info(' → Seeding Tickets for HK / RM / Security / Laundry / Campus Manager flows...');

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

            /** @var \App\Models\Student $student */
            $student = $students[$i % $students->count()];
            $assignee = $staffByRole[$category] ?? null;

            Ticket::create([
                'tenant_id' => $tenant->id,
                'hostel_id' => $student->hostel_id ?: $hostel->id,
                'title' => 'PPCU QA Ticket #' . ($i + 1) . " ({$category})",
                'description' => 'Seeded ticket for PPCU staff QA flows.',
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
}

