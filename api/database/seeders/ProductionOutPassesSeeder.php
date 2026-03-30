<?php

namespace Database\Seeders;

use App\Enums\OutPassStatus;
use App\Enums\OutPassType;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production OutPasses Seeder
 * 
 * Creates 50-100 outpasses per tenant with all statuses.
 */
class ProductionOutPassesSeeder extends Seeder
{
    private array $indianReasons = [
        'Going home for weekend',
        'Family function',
        'Medical appointment',
        'Personal work',
        'Shopping',
        'Friend\'s wedding',
        'Festival celebration',
        'Emergency at home',
        'College event',
        'Interview',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚪 Creating outpasses for each tenant...');

        $tenants = Tenant::all();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📋 Creating outpasses for {$tenant->name}...");
            
            $hostels = Hostel::where('tenant_id', $tenant->id)->get();
            $students = Student::where('tenant_id', $tenant->id)->get();
            
            if ($hostels->isEmpty() || $students->isEmpty()) {
                $this->command->warn("  ⚠️  Insufficient data for {$tenant->name}, skipping...");
                continue;
            }

            // Get rector for approvals
            $rector = User::where('tenant_id', $tenant->id)
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'Rector');
                })
                ->first();

            // Create 50-100 outpasses per tenant
            $outpassCount = rand(50, 100);
            $statusDistribution = [
                'pending' => 0.20,
                'approved' => 0.50,
                'declined' => 0.10,
                'expired' => 0.10,
                'cancelled' => 0.10,
            ];

            for ($i = 0; $i < $outpassCount; $i++) {
                $student = $students->random();
                $hostel = $hostels->where('id', $student->hostel_id)->first() ?? $hostels->random();
                
                // Determine status based on distribution
                $rand = rand(1, 100);
                $status = 'pending';
                $cumulative = 0;
                foreach ($statusDistribution as $stat => $prob) {
                    $cumulative += $prob * 100;
                    if ($rand <= $cumulative) {
                        $status = $stat;
                        break;
                    }
                }

                $reason = $this->indianReasons[array_rand($this->indianReasons)];
                $overnight = rand(1, 10) > 6; // 40% overnight
                $requestedAt = now()->subDays(rand(0, 30));
                $validUntil = $requestedAt->copy()->addHours(rand(4, 24));
                $decidedAt = $status !== 'pending' ? $requestedAt->copy()->addHours(rand(1, 6)) : null;

                // Expired if valid_until is in the past
                if ($status === 'expired' || ($validUntil->isPast() && $status === 'approved')) {
                    $status = 'expired';
                    $validUntil = now()->subDays(rand(1, 7));
                }

                OutPass::create([
                    'tenant_id' => $tenant->id,
                    'student_id' => $student->id,
                    'hostel_id' => $hostel->id,
                    'reason' => OutPassType::from(rand(1, 3) === 1 ? 'normal' : (rand(1, 2) === 1 ? 'leave' : 'sick')),
                    'overnight' => $overnight,
                    'status' => OutPassStatus::from($status),
                    'requested_at' => $requestedAt,
                    'decided_at' => $decidedAt,
                    'valid_until' => $validUntil,
                    'note' => $reason,
                    'decision_by' => $rector?->id,
                ]);

                $totalCreated++;
            }

            $this->command->info("  ✅ Created {$outpassCount} outpasses for {$tenant->name}");
        }

        $this->command->info("\n✅ Production outpasses seeding complete!");
        $this->command->info("Total outpasses created: {$totalCreated}");
    }
}

