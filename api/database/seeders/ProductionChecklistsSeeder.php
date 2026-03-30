<?php

namespace Database\Seeders;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistItem;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Production Checklists Seeder
 * 
 * Creates checklist templates and instances.
 */
class ProductionChecklistsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('✅ Creating checklists for each tenant...');

        $tenants = Tenant::all();
        $totalTemplates = 0;
        $totalInstances = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📋 Creating checklists for {$tenant->name}...");
            
            $hostels = Hostel::where('tenant_id', $tenant->id)->get();
            
            if ($hostels->isEmpty()) {
                $this->command->warn("  ⚠️  No hostels found for {$tenant->name}, skipping...");
                continue;
            }

            $templates = [
                'Guard' => [
                    ['code' => 'gate_log', 'label' => 'Update gate entry/exit log'],
                    ['code' => 'fire_exit', 'label' => 'Check fire exits are clear'],
                ],
                'Warden' => [
                    ['code' => 'hostel_round', 'label' => 'Complete hostel round'],
                    ['code' => 'incident_log', 'label' => 'Review incident log'],
                ],
                'HK Supervisor' => [
                    ['code' => 'common_area', 'label' => 'Inspect common areas', 'require_comment' => true],
                    ['code' => 'restrooms', 'label' => 'Inspect restrooms', 'require_photo' => true],
                ],
                'RM Supervisor' => [
                    ['code' => 'maintenance_queue', 'label' => 'Review maintenance queue', 'require_comment' => true],
                    ['code' => 'safety_checks', 'label' => 'Complete safety checks'],
                ],
            ];

            foreach ($templates as $role => $tasks) {
                $creator = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', $role))->first();

                $template = ChecklistTemplate::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'role' => $role,
                        'title' => $role . ' Daily Checklist',
                    ],
                    [
                        'tasks' => collect($tasks)->map(function ($task, $index) {
                            $label = $task['label'];
                            return [
                                'code' => $task['code'] ?? Str::slug("task-{$index}-{$label}"),
                                'label' => $label,
                                'require_photo' => $task['require_photo'] ?? false,
                                'require_comment' => $task['require_comment'] ?? false,
                            ];
                        })->toArray(),
                        'active' => true,
                        'created_by_user_id' => $creator?->id,
                    ]
                );

                $totalTemplates++;
            }

            $this->command->info("  ✅ Created checklists for {$tenant->name}");
        }

        $this->command->info("\n✅ Production checklists seeding complete!");
        $this->command->info("Total templates created: {$totalTemplates}");
        $this->command->info("Total instances created: {$totalInstances}");
    }
}

