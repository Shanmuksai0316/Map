<?php

namespace Database\Seeders;

use App\Models\Hostel;
use App\Models\Incident;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoIncidentsSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::whereIn('code', ['STXAV', 'NITKT', 'CHRUN', 'ANUN', 'DLART'])->get();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n🚨 Creating incidents for {$tenant->name}...");
            
            $tenant->run(function () use ($tenant, &$totalCreated) {
                $hostels = Hostel::all();
                $students = Student::limit(15)->get();
                $staff = User::on('pgsql')
                    ->where('tenant_id', $tenant->id)
                    ->where('kind', 'staff')
                    ->limit(5)
                    ->get();
                
                if ($hostels->isEmpty() || $students->isEmpty()) {
                    $this->command->warn("  ⚠️  Insufficient data for {$tenant->name}, skipping...");
                    return;
                }

                // Actual migration schema: title, description, severity, status, reporter_user_id, reporter_student_id, assigned_to_user_id, opened_at, resolved_at, resolution_notes
                $incidentTypes = [
                    ['title' => 'Late Return Incident', 'description' => 'Student returned after curfew time', 'severity' => 'medium'],
                    ['title' => 'Missed Attendance', 'description' => 'Student was absent during mandatory attendance check', 'severity' => 'low'],
                    ['title' => 'Emergency Exit', 'description' => 'Emergency exit taken for medical reasons. Family informed.', 'severity' => 'high'],
                    ['title' => 'Security Concern', 'description' => 'Security concern reported by hostel guard. Matter under investigation.', 'severity' => 'high'],
                    ['title' => 'Noise Complaint', 'description' => 'Excessive noise complaint from other students', 'severity' => 'low'],
                    ['title' => 'Unauthorized Visitor', 'description' => 'Visitor found in restricted area without proper authorization', 'severity' => 'critical'],
                ];
                
                $statuses = ['open', 'investigating', 'resolved', 'closed'];

                // Create 10 incidents per tenant
                for ($i = 0; $i < 10; $i++) {
                    $incidentData = $incidentTypes[array_rand($incidentTypes)];
                    $status = $statuses[array_rand($statuses)];
                    $reporter = $staff->isNotEmpty() ? $staff->random() : null;
                    $assigned = $staff->isNotEmpty() && $status !== 'open' ? $staff->random() : null;
                    $student = $students->random();

                    Incident::create([
                        'hostel_id' => $hostels->random()->id,
                        'reporter_user_id' => $reporter?->id,
                        'reporter_student_id' => $student->id,
                        'title' => $incidentData['title'],
                        'description' => $incidentData['description'] . ' - Reported on ' . now()->subDays(rand(1, 15))->format('Y-m-d'),
                        'severity' => $incidentData['severity'],
                        'status' => $status,
                        'assigned_to_user_id' => $assigned?->id,
                        'opened_at' => now()->subDays(rand(1, 15)),
                        'resolved_at' => in_array($status, ['resolved', 'closed']) ? now()->subDays(rand(0, 5)) : null,
                        'resolution_notes' => in_array($status, ['resolved', 'closed']) ? 'Matter resolved. No further action required.' : null,
                    ]);

                    $totalCreated++;
                }

                $this->command->info("  ✅ Created 10 incidents for {$tenant->name}");
            });
        }

        $this->command->info("\n✅ Demo incidents seeding complete!");
        $this->command->info("Total incidents created: {$totalCreated}");
    }
}

