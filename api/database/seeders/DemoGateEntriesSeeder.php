<?php

namespace Database\Seeders;

use App\Models\GateEntry;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoGateEntriesSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::whereIn('code', ['STXAV', 'NITKT', 'CHRUN', 'ANUN', 'DLART'])->get();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n🚪 Creating gate entries for {$tenant->name}...");
            
            $tenant->run(function () use ($tenant, &$totalCreated) {
                $students = Student::limit(30)->get();
                $guards = User::on('pgsql')
                    ->where('tenant_id', $tenant->id)
                    ->whereHas('roles', function($q) {
                        $q->where('name', 'Security');
                    })
                    ->limit(3)
                    ->get();
                
                if ($students->isEmpty()) {
                    $this->command->warn("  ⚠️  No students found for {$tenant->name}, skipping...");
                    return;
                }

                // Create 60-80 gate entries (last 30 days)
                $entriesCount = rand(60, 80);
                for ($i = 0; $i < $entriesCount; $i++) {
                    $student = $students->random();
                    $event = ['entry', 'exit'][array_rand(['entry', 'exit'])];
                    $guard = $guards->isNotEmpty() ? $guards->random() : null;
                    
                    GateEntry::create([
                        'student_id' => $student->id,
                        'guard_id' => $guard?->id,
                        'event' => $event,
                        'occurred_at' => now()->subDays(rand(0, 30))->setHours(rand(6, 22), rand(0, 59)),
                        'source' => ['Mobile', 'QR', 'Manual'][array_rand(['Mobile', 'QR', 'Manual'])],
                        'notes' => $event === 'exit' && rand(0, 5) === 0 ? 'Late exit approved' : null,
                    ]);

                    $totalCreated++;
                }

                $this->command->info("  ✅ Created {$entriesCount} gate entries for {$tenant->name}");
            });
        }

        $this->command->info("\n✅ Demo gate entries seeding complete!");
        $this->command->info("Total gate entries created: {$totalCreated}");
    }
}

