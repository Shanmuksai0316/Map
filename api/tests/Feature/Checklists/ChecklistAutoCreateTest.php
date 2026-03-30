<?php

namespace Tests\Feature\Checklists;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistItem;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Jobs\ChecklistAutoCreateDailyJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistAutoCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_daily_instances_for_active_templates(): void
    {
        config(['features.checklists_module' => true]);

        // Create Warden role for both guards
        \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Warden', 'guard_name' => 'web']
        );
        \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Warden', 'guard_name' => 'sanctum']
        );

        $tenant = Tenant::factory()->create(['code' => 'AUTO-TENANT']);

        $assignees = User::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Warden',
        ]);
        $assignees->each(fn (User $user) => $user->assignRole('Warden'));

        $template = ChecklistTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'role' => 'Warden',
            'title' => 'Daily Campus Walkthrough',
            'tasks' => [
                ['code' => 'entrance', 'label' => 'Check entrance'],
                ['code' => 'mess', 'label' => 'Inspect mess area'],
                ['code' => 'lights', 'label' => 'Verify corridor lights'],
            ],
            'active' => true,
            'created_by_user_id' => $assignees->first()->id,
        ]);

        $job = new ChecklistAutoCreateDailyJob();
        $repository = app(\App\Domain\Checklists\Repositories\ChecklistInstanceRepository::class);

        $job->handle($repository);
        $job->handle($repository);

        $dateIst = \Illuminate\Support\Carbon::now('Asia/Kolkata')->toDateString();

        foreach ($assignees as $user) {
            $count = ChecklistInstance::query()
                ->where('tenant_id', $tenant->id)
                ->where('template_id', $template->id)
                ->whereDate('date', $dateIst)
                ->where('shift', 'Daily')
                ->where('assignee_user_id', $user->id)
                ->count();

            $this->assertSame(1, $count);
        }

        $this->assertSame(3 * $assignees->count(), ChecklistItem::count());
    }
}

