<?php

namespace Database\Seeders;

use App\Enums\LaundryRequestStatus;
use App\Enums\LaundryServiceType;
use App\Models\Hostel;
use App\Models\LaundryCycle;
use App\Models\LaundryRequest;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production Laundry Seeder
 * 
 * Creates laundry cycles and requests with all statuses.
 */
class ProductionLaundrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🧺 Creating laundry data for each tenant...');

        $tenants = Tenant::where('addon_laundry', true)->get();
        $totalCycles = 0;
        $totalRequests = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📋 Creating laundry data for {$tenant->name}...");
            
            $hostels = Hostel::where('tenant_id', $tenant->id)->get();
            $students = Student::where('tenant_id', $tenant->id)->limit(50)->get();
            
            if ($hostels->isEmpty() || $students->isEmpty()) {
                $this->command->warn("  ⚠️  Insufficient data for {$tenant->name}, skipping...");
                continue;
            }

            // Get laundry manager
            $laundryManager = User::where('tenant_id', $tenant->id)
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'Laundry Manager');
                })
                ->first();

            // Create laundry cycles
            foreach ($hostels as $hostel) {
                // Create 2-3 cycles per hostel
                $cycleCount = rand(2, 3);
                $statusDistribution = ['scheduled' => 0.30, 'in_progress' => 0.20, 'completed' => 0.50];

                for ($i = 0; $i < $cycleCount; $i++) {
                    $rand = rand(1, 100);
                    $status = 'scheduled';
                    $cumulative = 0;
                    foreach ($statusDistribution as $stat => $prob) {
                        $cumulative += $prob * 100;
                        if ($rand <= $cumulative) {
                            $status = $stat;
                            break;
                        }
                    }

                    $startedAt = $status !== 'scheduled' ? now()->subDays(rand(0, 7)) : null;
                    $completedAt = $status === 'completed' ? ($startedAt ?? now())->copy()->addHours(rand(2, 6)) : null;

                    $cycle = LaundryCycle::create([
                        'hostel_id' => $hostel->id,
                        'machine_label' => 'Machine ' . chr(65 + $i), // A, B, C
                        'status' => $status,
                        'started_at' => $startedAt,
                        'completed_at' => $completedAt,
                        'operator_id' => $laundryManager?->id,
                    ]);

                    $totalCycles++;

                    // Create requests for this cycle
                    $hostelStudents = $students->where('hostel_id', $hostel->id)->take(rand(5, 15));
                    $requestStatusDistribution = ['requested' => 0.20, 'processing' => 0.20, 'ready' => 0.20, 'completed' => 0.40];

                    foreach ($hostelStudents as $student) {
                        $rand = rand(1, 100);
                        $requestStatus = 'requested';
                        $cumulative = 0;
                        foreach ($requestStatusDistribution as $stat => $prob) {
                            $cumulative += $prob * 100;
                            if ($rand <= $cumulative) {
                                $requestStatus = $stat;
                                break;
                            }
                        }

                        $requestedAt = now()->subDays(rand(0, 7));
                        $readyAt = in_array($requestStatus, ['ready', 'completed']) ? $requestedAt->copy()->addHours(rand(12, 48)) : null;
                        $completedAt = $requestStatus === 'completed' ? ($readyAt ?? $requestedAt)->copy()->addHours(rand(2, 12)) : null;

                        LaundryRequest::create([
                            'campus_id' => $hostel->campus_id,
                            'hostel_id' => $hostel->id,
                            'student_id' => $student->id,
                            'laundry_cycle_id' => $cycle->id,
                            'service_type' => LaundryServiceType::from(['wash', 'dry_clean', 'iron'][array_rand(['wash', 'dry_clean', 'iron'])]),
                            'status' => LaundryRequestStatus::from($requestStatus),
                            'bag_count' => rand(1, 3),
                            'weight_kg' => rand(2, 8) + (rand(0, 9) / 10), // 2.0 to 8.9 kg
                            'requested_at' => $requestedAt,
                            'ready_at' => $readyAt,
                            'completed_at' => $completedAt,
                        ]);

                        $totalRequests++;
                    }
                }
            }

            $this->command->info("  ✅ Created laundry data for {$tenant->name}");
        }

        $this->command->info("\n✅ Production laundry seeding complete!");
        $this->command->info("Total cycles created: {$totalCycles}");
        $this->command->info("Total requests created: {$totalRequests}");
    }
}

