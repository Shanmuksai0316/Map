<?php

namespace Database\Seeders;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketComment;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoTicketsSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::whereIn('code', ['STXAV', 'NITKT', 'CHRUN', 'ANUN', 'DLART'])->get();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n🎫 Creating tickets for {$tenant->name}...");
            
            $tenant->run(function () use ($tenant, &$totalCreated) {
                $hostels = Hostel::all();
                $students = Student::limit(20)->get();
                
                // Get staff users from central DB
                $staff = User::on('pgsql')
                    ->where('tenant_id', $tenant->id)
                    ->where('kind', 'staff')
                    ->limit(10)
                    ->get();
                
                if ($hostels->isEmpty() || $students->isEmpty()) {
                    $this->command->warn("  ⚠️  Insufficient data for {$tenant->name}, skipping...");
                    return;
                }

                $categories = ['Maintenance', 'Electrical', 'Plumbing', 'IT', 'Housekeeping', 'Furniture', 'AC'];
                $priorities = ['Low', 'Medium', 'High', 'Critical'];
                $statuses = ['Open', 'InProgress', 'Resolved', 'Closed'];

                $ticketScenarios = [
                    ['title' => 'AC not cooling properly', 'description' => 'The AC in room 201 is not cooling. It has been blowing warm air since yesterday.', 'category' => 'AC', 'priority' => 'High'],
                    ['title' => 'Broken window pane', 'description' => 'Window glass in room 305 is cracked and needs replacement for safety.', 'category' => 'Maintenance', 'priority' => 'Medium'],
                    ['title' => 'Wi-Fi not working', 'description' => 'Unable to connect to hostel Wi-Fi network. Getting authentication error.', 'category' => 'IT', 'priority' => 'High'],
                    ['title' => 'Leaking tap in bathroom', 'description' => 'The tap in the common bathroom has been leaking for 3 days. Water wastage is high.', 'category' => 'Plumbing', 'priority' => 'Medium'],
                    ['title' => 'Broken study table', 'description' => 'Study table leg is broken. Need repair or replacement.', 'category' => 'Furniture', 'priority' => 'Low'],
                    ['title' => 'Faulty electrical socket', 'description' => 'Power socket near bed is not working. Sparking noticed.', 'category' => 'Electrical', 'priority' => 'Critical'],
                    ['title' => 'Room cleaning not done', 'description' => 'Housekeeping staff skipped room 105 during daily cleaning for 2 days.', 'category' => 'Housekeeping', 'priority' => 'Low'],
                    ['title' => 'Door lock jammed', 'description' => 'Room door lock is jammed. Having difficulty entering room.', 'category' => 'Maintenance', 'priority' => 'High'],
                    ['title' => 'Water supply disruption', 'description' => 'No water supply on 3rd floor since morning.', 'category' => 'Plumbing', 'priority' => 'Critical'],
                    ['title' => 'Ceiling fan making noise', 'description' => 'Ceiling fan in room 208 is making loud rattling noise and wobbling.', 'category' => 'Electrical', 'priority' => 'Medium'],
                ];

                // Create 15-20 tickets per tenant
                for ($i = 0; $i < 18; $i++) {
                    $scenario = $ticketScenarios[$i % count($ticketScenarios)];
                    $hostel = $hostels->random();
                    $reporterStudent = $students->random();
                    $status = $statuses[array_rand($statuses)];
                    $assignee = $staff->isNotEmpty() ? $staff->random() : null;

                    // Map priority for actual schema (Critical -> urgent, others lowercase)
                    $schemaPriority = match($scenario['priority']) {
                        'Critical' => 'urgent',
                        'High' => 'high',
                        'Medium' => 'medium',
                        default => 'low',
                    };
                    
                    // Map status for actual schema (all lowercase with underscore)
                    $schemaStatus = match($status) {
                        'Open' => 'open',
                        'InProgress' => 'in_progress',
                        'Resolved' => 'resolved',
                        'Closed' => 'closed',
                        default => 'open',
                    };
                    
                    // Map category to actual schema values
                    $schemaCategory = match($scenario['category']) {
                        'Electrical' => 'electrical',
                        'Plumbing' => 'plumbing',
                        'AC', 'Maintenance' => 'maintenance',
                        'Furniture' => 'maintenance',
                        'IT' => 'other',
                        'Housekeeping' => 'cleaning',
                        default => 'maintenance',
                    };

                    $ticket = Ticket::create([
                        'hostel_id' => $hostel->id,
                        'category' => $schemaCategory,
                        'priority' => $schemaPriority,
                        'status' => $schemaStatus,
                        'reporter_student_id' => $reporterStudent->id,
                        'reporter_user_id' => (int)$reporterStudent->user_id,
                        'assignee_user_id' => $assignee?->id,
                        'title' => $scenario['title'],
                        'description' => $scenario['description'],
                        'opened_at' => now()->subDays(rand(1, 15)),
                        'resolved_at' => in_array($schemaStatus, ['resolved', 'closed']) ? now()->subDays(rand(0, 5)) : null,
                        'resolution_notes' => in_array($schemaStatus, ['resolved', 'closed']) ? 'Issue has been resolved.' : null,
                    ]);

                    // Skip comments for now - visibility column doesn't exist in migration

                    $totalCreated++;
                }

                $this->command->info("  ✅ Created 18 tickets for {$tenant->name}");
            });
        }

        $this->command->info("\n✅ Demo tickets seeding complete!");
        $this->command->info("Total tickets created: {$totalCreated}");
    }
}

