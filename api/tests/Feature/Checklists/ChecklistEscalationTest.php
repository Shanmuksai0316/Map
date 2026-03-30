<?php

namespace Tests\Feature\Checklists;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistJobEvent;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Jobs\ChecklistEscalationJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\ChecklistNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ChecklistEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_escalation_is_triggered_at_t_plus_60_and_deduplicated(): void
    {
        config(['features.checklists_module' => true]);

        // Create Warden role for both guards
        \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Warden', 'guard_name' => 'web']
        );
        \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Warden', 'guard_name' => 'sanctum']
        );

        $tenant = Tenant::factory()->create();
        $warden = User::factory()->create(['tenant_id' => $tenant->id]);
        $warden->assignRole('Warden');

        $template = ChecklistTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'role' => 'Warden',
            'title' => 'Daily Check',
            'tasks' => [
                ['code' => 'task1', 'label' => 'Task 1'],
            ],
            'active' => true,
            'created_by_user_id' => $warden->id,
        ]);

        $dueTime = Carbon::create(2025, 9, 29, 21, 30, 0, 'Asia/Kolkata');

        $instance = ChecklistInstance::query()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'date' => $dueTime->toDateString(),
            'shift' => 'Daily',
            'role' => 'Warden',
            'assignee_user_id' => $warden->id,
            'status' => 'Pending',
            'total_tasks' => 1,
            'completed_tasks' => 0,
        ]);

        // Test escalation at T+60
        Carbon::setTestNow($dueTime->copy()->addMinutes(61));
        (new ChecklistEscalationJob())->handle(app(ChecklistNotifier::class));

        $this->assertDatabaseHas('checklist_job_events', [
            'instance_id' => $instance->id,
            'event_type' => 'escalation',
        ]);

        // Run again, should not create duplicate
        (new ChecklistEscalationJob())->handle(app(ChecklistNotifier::class));
        $this->assertEquals(1, ChecklistJobEvent::query()
            ->where('event_type', 'escalation')
            ->count());
    }

    public function test_escalation_does_not_trigger_before_t_plus_60(): void
    {
        config(['features.checklists_module' => true]);

        // Create Warden role for both guards
        \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Warden', 'guard_name' => 'web']
        );
        \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Warden', 'guard_name' => 'sanctum']
        );

        $tenant = Tenant::factory()->create();
        $warden = User::factory()->create(['tenant_id' => $tenant->id]);
        $warden->assignRole('Warden');

        $template = ChecklistTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'role' => 'Warden',
            'title' => 'Daily Check',
            'tasks' => [
                ['code' => 'task1', 'label' => 'Task 1'],
            ],
            'active' => true,
            'created_by_user_id' => $warden->id,
        ]);

        $dueTime = Carbon::create(2025, 9, 29, 21, 30, 0, 'Asia/Kolkata');

        $instance = ChecklistInstance::query()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'date' => $dueTime->toDateString(),
            'shift' => 'Daily',
            'role' => 'Warden',
            'assignee_user_id' => $warden->id,
            'status' => 'Pending',
            'total_tasks' => 1,
            'completed_tasks' => 0,
        ]);

        // Test before escalation time (T+30)
        Carbon::setTestNow($dueTime->copy()->addMinutes(30));
        (new ChecklistEscalationJob())->handle(app(ChecklistNotifier::class));

        $this->assertDatabaseMissing('checklist_job_events', [
            'instance_id' => $instance->id,
            'event_type' => 'escalation',
        ]);
    }
}
