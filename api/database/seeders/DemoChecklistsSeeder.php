<?php

namespace Database\Seeders;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistItem;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoChecklistsSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::whereIn('code', ['STXAV', 'NIT', 'CHRIST', 'ANNA', 'DCA'])->get();
        $totalTemplates = 0;
        $totalInstances = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📋 Creating checklists for {$tenant->name}...");
            
            // Templates in central DB
            $templates = [
                [
                    'role' => 'Warden',
                    'title' => 'Daily Warden Checklist',
                    'tasks' => ['Check all floors', 'Review incident reports', 'Verify attendance', 'Inspect common areas', 'Review pending requests'],
                ],
                [
                    'role' => 'Security',
                    'title' => 'Security Shift Checklist',
                    'tasks' => ['Patrol hostel perimeter', 'Check CCTV recordings', 'Verify visitor logs', 'Test emergency alarms', 'Update duty log'],
                ],
                [
                    'role' => 'Maintenance',
                    'title' => 'Maintenance Daily Checklist',
                    'tasks' => ['Check water supply', 'Inspect electrical panels', 'Test backup generators', 'Review pending tickets', 'Update equipment log'],
                ],
                [
                    'role' => 'Housekeeping',
                    'title' => 'Housekeeping Daily Checklist',
                    'tasks' => ['Clean common areas', 'Sanitize bathrooms', 'Empty trash bins', 'Restock supplies', 'Report damages'],
                ],
            ];

            foreach ($templates as $tmpl) {
                $template = ChecklistTemplate::create([
                    'tenant_id' => $tenant->id,
                    'role' => $tmpl['role'],
                    'title' => $tmpl['title'],
                    'tasks' => $tmpl['tasks'],
                    'active' => true,
                    'created_by_user_id' => null,
                ]);
                $totalTemplates++;

                // Create instances for each template (last 30 days)
                $staffRole = $tmpl['role'];
                $staff = User::on('pgsql')
                    ->where('tenant_id', $tenant->id)
                    ->whereHas('roles', function($q) use ($staffRole) {
                        $q->where('name', $staffRole);
                    })
                    ->limit(3)
                    ->get();

                if ($staff->isEmpty()) {
                    continue; // Skip if no staff for this role
                }

                // Create 5-7 instances per template
                for ($day = 0; $day < 7; $day++) {
                    $date = now()->subDays($day);
                    $assignee = $staff->random();
                    $status = $day < 2 ? ChecklistInstance::STATUS_PENDING : (
                        $day < 5 ? ChecklistInstance::STATUS_SUBMITTED : ChecklistInstance::STATUS_APPROVED
                    );

                    $instance = ChecklistInstance::create([
                        'tenant_id' => $tenant->id,
                        'template_id' => $template->id,
                        'date' => $date,
                        'shift' => ['Morning', 'Evening', 'Night'][array_rand(['Morning', 'Evening', 'Night'])],
                        'role' => $tmpl['role'],
                        'assignee_user_id' => $assignee->id,
                        'status' => $status,
                        'total_tasks' => count($tmpl['tasks']),
                        'completed_tasks' => $status === ChecklistInstance::STATUS_PENDING ? 0 : count($tmpl['tasks']),
                        'submitted_at' => in_array($status, [ChecklistInstance::STATUS_SUBMITTED, ChecklistInstance::STATUS_APPROVED]) ? $date->addHours(8) : null,
                        'manager_user_id' => $status === ChecklistInstance::STATUS_APPROVED ? $assignee->id : null,
                        'manager_note' => $status === ChecklistInstance::STATUS_APPROVED ? 'All tasks completed satisfactorily.' : null,
                        'reviewed_at' => $status === ChecklistInstance::STATUS_APPROVED ? $date->addHours(10) : null,
                    ]);

                    // Create checklist items for each task
                    foreach ($tmpl['tasks'] as $idx => $task) {
                        ChecklistItem::create([
                            'instance_id' => $instance->id,
                            'task' => $task,
                            'sequence' => $idx + 1,
                            'completed' => $status !== ChecklistInstance::STATUS_PENDING,
                            'completed_at' => $status !== ChecklistInstance::STATUS_PENDING ? $date->addHours(rand(1, 7)) : null,
                        ]);
                    }

                    $totalInstances++;
                }
            }

            $this->command->info("  ✅ Created " . count($templates) . " templates and instances for {$tenant->name}");
        }

        $this->command->info("\n✅ Demo checklists seeding complete!");
        $this->command->info("Total templates created: {$totalTemplates}");
        $this->command->info("Total instances created: {$totalInstances}");
    }
}

