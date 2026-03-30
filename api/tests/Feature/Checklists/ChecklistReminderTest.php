<?php

namespace Tests\Feature\Checklists;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistJobEvent;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Jobs\ChecklistReminderJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\ChecklistNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ChecklistReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminders_are_sent_at_t_minus_60_and_t_minus_15(): void
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

        // Test T-60 reminder window
        Carbon::setTestNow($dueTime->copy()->subMinutes(55));
        (new ChecklistReminderJob())->handle(app(ChecklistNotifier::class));

        $this->assertDatabaseHas('checklist_job_events', [
            'instance_id' => $instance->id,
            'event_type' => 'reminder',
            'phase' => 'T-60',
        ]);

        // Run again, should not create duplicate
        (new ChecklistReminderJob())->handle(app(ChecklistNotifier::class));
        $this->assertEquals(1, ChecklistJobEvent::query()
            ->where('event_type', 'reminder')
            ->where('phase', 'T-60')
            ->count());

        // Test T-15 reminder window
        Carbon::setTestNow($dueTime->copy()->subMinutes(10));
        (new ChecklistReminderJob())->handle(app(ChecklistNotifier::class));

        $this->assertDatabaseHas('checklist_job_events', [
            'instance_id' => $instance->id,
            'event_type' => 'reminder',
            'phase' => 'T-15',
        ]);

        // Run again, should not create duplicate
        (new ChecklistReminderJob())->handle(app(ChecklistNotifier::class));
        $this->assertEquals(1, ChecklistJobEvent::query()
            ->where('event_type', 'reminder')
            ->where('phase', 'T-15')
            ->count());

        // Total should be 2 events (T-60 and T-15)
        $this->assertEquals(2, ChecklistJobEvent::query()
            ->where('instance_id', $instance->id)
            ->where('event_type', 'reminder')
            ->count());
    }
}
