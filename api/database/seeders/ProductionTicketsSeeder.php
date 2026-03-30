<?php

namespace Database\Seeders;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketComment;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production Tickets Seeder
 * 
 * Creates 50-100 tickets per tenant with all categories and statuses.
 */
class ProductionTicketsSeeder extends Seeder
{
    private array $ticketScenarios = [
        ['title' => 'AC not cooling properly', 'description' => 'The AC in room is not cooling. It has been blowing warm air since yesterday.', 'category' => 'maintenance', 'priority' => 'high'],
        ['title' => 'Broken window pane', 'description' => 'Window glass is cracked and needs replacement for safety.', 'category' => 'maintenance', 'priority' => 'medium'],
        ['title' => 'Wi-Fi not working', 'description' => 'Unable to connect to hostel Wi-Fi network. Getting authentication error.', 'category' => 'other', 'priority' => 'high'],
        ['title' => 'Leaking tap in bathroom', 'description' => 'The tap in the common bathroom has been leaking for 3 days. Water wastage is high.', 'category' => 'plumbing', 'priority' => 'medium'],
        ['title' => 'Broken study table', 'description' => 'Study table leg is broken. Need repair or replacement.', 'category' => 'maintenance', 'priority' => 'low'],
        ['title' => 'Faulty electrical socket', 'description' => 'Power socket near bed is not working. Sparking noticed.', 'category' => 'electrical', 'priority' => 'urgent'],
        ['title' => 'Room cleaning not done', 'description' => 'Housekeeping staff skipped room during daily cleaning for 2 days.', 'category' => 'cleaning', 'priority' => 'low'],
        ['title' => 'Door lock jammed', 'description' => 'Room door lock is jammed. Having difficulty entering room.', 'category' => 'maintenance', 'priority' => 'high'],
        ['title' => 'Water supply disruption', 'description' => 'No water supply on floor since morning.', 'category' => 'plumbing', 'priority' => 'urgent'],
        ['title' => 'Ceiling fan making noise', 'description' => 'Ceiling fan is making loud rattling noise and wobbling.', 'category' => 'electrical', 'priority' => 'medium'],
        ['title' => 'Bed frame is loose', 'description' => 'Bed frame is loose and making noise when moving.', 'category' => 'maintenance', 'priority' => 'low'],
        ['title' => 'Wardrobe door off track', 'description' => 'Wardrobe door is off track and not closing properly.', 'category' => 'maintenance', 'priority' => 'low'],
        ['title' => 'Fan speed control issue', 'description' => 'Fan speed control is not working. Stuck on one speed.', 'category' => 'electrical', 'priority' => 'medium'],
        ['title' => 'Power socket not working', 'description' => 'Power socket near study table is not working.', 'category' => 'electrical', 'priority' => 'high'],
        ['title' => 'Curtain rod falling', 'description' => 'Curtain rod is falling and needs fixing.', 'category' => 'maintenance', 'priority' => 'low'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎫 Creating tickets for each tenant...');

        $tenants = Tenant::all();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📋 Creating tickets for {$tenant->name}...");
            
            $hostels = Hostel::where('tenant_id', $tenant->id)->get();
            $students = Student::where('tenant_id', $tenant->id)->limit(50)->get();
            
            if ($hostels->isEmpty() || $students->isEmpty()) {
                $this->command->warn("  ⚠️  Insufficient data for {$tenant->name}, skipping...");
                continue;
            }

            // Get staff for assignments
            $staff = User::where('tenant_id', $tenant->id)
                ->where('kind', 'staff')
                ->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['HK Supervisor', 'RM Supervisor', 'Campus Manager']);
                })
                ->get();

            $statuses = ['open', 'in_progress', 'resolved', 'closed'];
            $statusDistribution = ['open' => 0.30, 'in_progress' => 0.25, 'resolved' => 0.30, 'closed' => 0.15];

            // Create 50-100 tickets per tenant
            $ticketCount = rand(50, 100);

            for ($i = 0; $i < $ticketCount; $i++) {
                $scenario = $this->ticketScenarios[array_rand($this->ticketScenarios)];
                $hostel = $hostels->random();
                $reporterStudent = $students->random();
                
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

                $assignee = $status !== 'open' && $staff->isNotEmpty() ? $staff->random() : null;

                $ticket = Ticket::create([
                    'tenant_id' => $tenant->id,
                    'hostel_id' => $hostel->id,
                    'category' => $scenario['category'],
                    'priority' => $scenario['priority'],
                    'status' => $status,
                    'reporter_student_id' => $reporterStudent->id,
                    'reporter_user_id' => (int) $reporterStudent->user_id,
                    'assignee_user_id' => $assignee?->id,
                    'title' => $scenario['title'],
                    'description' => $scenario['description'],
                    'opened_at' => now()->subDays(rand(1, 30)),
                    'resolved_at' => in_array($status, ['resolved', 'closed']) ? now()->subDays(rand(0, 10)) : null,
                    'resolution_notes' => in_array($status, ['resolved', 'closed']) ? 'Issue has been resolved.' : null,
                ]);

                // Add comments for in-progress/resolved tickets
                if (in_array($status, ['in_progress', 'resolved', 'closed'])) {
                    $commentCount = rand(1, 3);
                    for ($j = 0; $j < $commentCount; $j++) {
                        TicketComment::create([
                            'ticket_id' => $ticket->id,
                            'user_id' => $assignee?->id ?? $reporterStudent->user_id,
                            'body' => 'Working on this issue. Will update soon.',
                            'attachments' => null,
                            'is_internal' => $j > 0,
                        ]);
                    }
                }

                $totalCreated++;
            }

            $this->command->info("  ✅ Created {$ticketCount} tickets for {$tenant->name}");
        }

        $this->command->info("\n✅ Production tickets seeding complete!");
        $this->command->info("Total tickets created: {$totalCreated}");
    }
}

