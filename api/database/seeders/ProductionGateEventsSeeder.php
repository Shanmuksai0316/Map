<?php

namespace Database\Seeders;

use App\Models\GateEntry;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production Gate Events Seeder
 * 
 * Creates 500-1000 gate events per tenant (IN/OUT with various methods).
 */
class ProductionGateEventsSeeder extends Seeder
{
    private array $methods = ['QR', 'LIST', 'OTP', 'MANUAL', 'EMERGENCY'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚪 Creating gate events for each tenant...');

        $tenants = Tenant::all();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📋 Creating gate events for {$tenant->name}...");
            
            $hostels = Hostel::where('tenant_id', $tenant->id)->get();
            $students = Student::where('tenant_id', $tenant->id)->get();
            
            if ($hostels->isEmpty() || $students->isEmpty()) {
                $this->command->warn("  ⚠️  Insufficient data for {$tenant->name}, skipping...");
                continue;
            }

            // Get guards
            $guards = User::where('tenant_id', $tenant->id)
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'Guard');
                })
                ->get();

            // Create 500-1000 events per tenant
            $eventCount = rand(500, 1000);

            for ($i = 0; $i < $eventCount; $i++) {
                $student = $students->random();
                $hostel = $hostels->where('id', $student->hostel_id)->first() ?? $hostels->random();
                $guard = $guards->isNotEmpty() ? $guards->random() : null;
                
                // Random event in past 30 days
                $occurredAt = now()->subDays(rand(0, 30))
                    ->setTime(rand(6, 23), rand(0, 59));
                
                // Direction: 60% OUT, 40% IN
                $direction = rand(1, 10) <= 6 ? 'OUT' : 'IN';
                $method = $this->methods[array_rand($this->methods)];

                GateEntry::create([
                    'student_id' => $student->id,
                    'guard_id' => $guard?->id,
                    'event' => $direction,
                    'occurred_at' => $occurredAt,
                    'source' => $method,
                ]);

                $totalCreated++;
            }

            $this->command->info("  ✅ Created {$eventCount} gate events for {$tenant->name}");
        }

        $this->command->info("\n✅ Production gate events seeding complete!");
        $this->command->info("Total gate events created: {$totalCreated}");
    }
}

