<?php

namespace App\Console\Commands;

use App\Models\Domain\OutPass\OutPass;
use App\Models\Student;
use App\Models\Tenant;
use App\Enums\OutPassStatus;
use App\Enums\OutPassType;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateTestOutpasses extends Command
{
    protected $signature = 'outpass:create-test {tenant_code?} {--count=5}';
    protected $description = 'Create test outpass requests for approval/rejection testing';

    public function handle()
    {
        $tenantCode = $this->argument('tenant_code');
        $count = (int) $this->option('count');

        // Get tenant
        if ($tenantCode) {
            $tenant = Tenant::where('code', $tenantCode)->first();
            if (!$tenant) {
                $this->error("Tenant with code '{$tenantCode}' not found.");
                return 1;
            }
        } else {
            // Get first active tenant
            $tenant = Tenant::where('status', 'active')->first();
            if (!$tenant) {
                $this->error('No active tenant found. Please specify tenant code.');
                $this->info('Usage: php artisan outpass:create-test <tenant_code>');
                return 1;
            }
        }

        $this->info("Using tenant: {$tenant->name} (Code: {$tenant->code})");
        $this->info("Tenant ID: {$tenant->id}\n");

        // Get students for this tenant
        $students = Student::where('tenant_id', $tenant->id)
            ->whereNotNull('hostel_id')
            ->limit($count * 2) // Get more students in case some already have requests
            ->get();

        if ($students->isEmpty()) {
            $this->error('No students found for this tenant with hostel assignments.');
            return 1;
        }

        $this->info("Found {$students->count()} students\n");

        // Create test outpass requests
        $reasons = ['normal', 'leave', 'sick'];
        $now = now('Asia/Kolkata');
        $created = 0;

        // Loop through count, reusing students if needed
        for ($i = 0; $i < $count; $i++) {
            // Cycle through available students
            $student = $students[$i % count($students)];
            $reason = $reasons[$i % count($reasons)];
            
            // Create requests for different dates (tomorrow onwards to avoid conflicts)
            $requestDate = $now->copy()->addDays($i + 1); // Start from tomorrow, different date for each
            $validUntil = $requestDate->copy()->addHours(8);

            // Check if outpass already exists for this student on this specific date
            $existing = OutPass::where('tenant_id', $tenant->id)
                ->where('student_id', $student->id)
                ->where('requested_for', $requestDate->toDateString())
                ->where('status', OutPassStatus::PENDING)
                ->first();

            if ($existing) {
                $this->warn("Student {$student->full_name} already has a pending outpass request for {$requestDate->toDateString()}. Skipping.");
                continue;
            }

            $outpass = OutPass::create([
                'tenant_id' => $tenant->id,
                'student_id' => $student->id,
                'hostel_id' => $student->hostel_id,
                'reason' => OutPassType::from($reason),
                'overnight' => $i % 3 === 0, // Every 3rd one is overnight
                'status' => OutPassStatus::PENDING,
                'requested_at' => $requestDate,
                'requested_for' => $requestDate->toDateString(),
                'valid_until' => $validUntil,
                'note' => $i === 0 ? 'Urgent medical appointment' : ($i === 1 ? 'Family emergency' : ($i === 2 ? 'Personal work' : ($i === 3 ? 'College event' : null))),
                'idempotency_key' => (string) Str::uuid(),
            ]);

            // Record history (skip if table structure doesn't support it)
            try {
                $outpass->recordHistory(
                    null,
                    OutPassStatus::PENDING,
                    'Test outpass request created',
                    null,
                    'Request Submitted',
                    'Test outpass request created for testing approval/rejection'
                );
            } catch (\Exception $e) {
                // History recording failed, but outpass was created successfully
                $this->warn("Note: Could not record history: " . $e->getMessage());
            }

            $created++;
            $this->info("✅ Created outpass #{$outpass->id} for {$student->full_name} ({$reason}, " . ($outpass->overnight ? 'overnight' : 'day') . ")");
        }

        $this->newLine();
        $this->info("========================================");
        $this->info("✅ Created {$created} test outpass requests");
        $this->info("========================================");
        $this->newLine();
        $this->info("You can now test approval and rejection in:");
        $this->info("- Rector mobile app");
        $this->info("- Rector web panel");
        $this->info("- Campus Manager web panel");

        return 0;
    }
}
