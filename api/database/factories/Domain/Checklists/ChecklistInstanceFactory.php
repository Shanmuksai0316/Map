<?php

namespace Database\Factories\Domain\Checklists;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChecklistInstanceFactory extends Factory
{
    protected $model = ChecklistInstance::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $creator = User::factory()->create(['tenant_id' => $tenant->id]);
        $assignee = User::factory()->create(['tenant_id' => $tenant->id]);

        $template = ChecklistTemplate::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $creator->id,
        ]);

        return [
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'date' => now()->toDateString(),
            'shift' => 'Daily',
            'role' => $template->role,
            'assignee_user_id' => $assignee->id,
            'status' => ChecklistInstance::STATUS_PENDING,
            'total_tasks' => count($template->tasks ?? []),
            'completed_tasks' => 0,
        ];
    }
}

