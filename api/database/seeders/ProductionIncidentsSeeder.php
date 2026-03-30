<?php

namespace Database\Seeders;

use App\Models\Hostel;
use App\Models\Incident;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production Incidents Seeder
 * 
 * Creates incidents (LateReturn, MissedAttendance, EmergencyExit, Security).
 */
class ProductionIncidentsSeeder extends Seeder
{
    private array $incidentTypes = ['LateReturn', 'MissedAttendance', 'EmergencyExit', 'Security'];
    private array $incidentNotes = [
        'LateReturn' => 'Student returned after curfew time.',
        'MissedAttendance' => 'Student missed attendance check.',
        'EmergencyExit' => 'Emergency exit used without permission.',
        'Security' => 'Security concern reported.',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('⚠️  Creating incidents for each tenant...');

        $tenants = Tenant::all();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📋 Creating incidents for {$tenant->name}...");
            
            $hostels = Hostel::where('tenant_id', $tenant->id)->get();
            $students = Student::where('tenant_id', $tenant->id)->limit(20)->get();
            
            if ($hostels->isEmpty() || $students->isEmpty()) {
                $this->command->warn("  ⚠️  Insufficient data for {$tenant->name}, skipping...");
                continue;
            }

            // Get staff for opening incidents
            $staff = User::where('tenant_id', $tenant->id)
                ->where('kind', 'staff')
                ->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['Guard', 'Warden', 'Campus Manager']);
                })
                ->get();

            // Create 20-30 incidents per tenant
            $incidentCount = rand(20, 30);
            $statusDistribution = ['open' => 0.40, 'closed' => 0.60];

            for ($i = 0; $i < $incidentCount; $i++) {
                $type = $this->incidentTypes[array_rand($this->incidentTypes)];
                $hostel = $hostels->random();
                $student = rand(1, 10) > 3 ? $students->random() : null; // 70% have student
                $openedBy = $staff->isNotEmpty() ? $staff->random() : null;
                
                // Determine status
                $rand = rand(1, 100);
                $status = 'open';
                $cumulative = 0;
                foreach ($statusDistribution as $stat => $prob) {
                    $cumulative += $prob * 100;
                    if ($rand <= $cumulative) {
                        $status = $stat;
                        break;
                    }
                }

                $openedAt = now()->subDays(rand(0, 30));
                $closedAt = $status === 'closed' ? $openedAt->copy()->addDays(rand(1, 5)) : null;
                $closedBy = $status === 'closed' && $staff->isNotEmpty() ? $staff->random() : null;

                Incident::create([
                    'hostel_id' => $hostel->id,
                    'type' => $type,
                    'student_id' => $student?->id,
                    'note' => $this->incidentNotes[$type],
                    'status' => $status,
                    'opened_by' => $openedBy?->id,
                    'opened_at' => $openedAt,
                    'closed_by' => $closedBy?->id,
                    'closed_at' => $closedAt,
                ]);

                $totalCreated++;
            }

            $this->command->info("  ✅ Created {$incidentCount} incidents for {$tenant->name}");
        }

        $this->command->info("\n✅ Production incidents seeding complete!");
        $this->command->info("Total incidents created: {$totalCreated}");
    }
}

