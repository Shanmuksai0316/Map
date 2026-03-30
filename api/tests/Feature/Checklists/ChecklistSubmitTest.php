<?php

namespace Tests\Feature\Checklists;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistItem;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Jobs\ChecklistAutoCreateDailyJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChecklistSubmitTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignee_can_mark_items_and_submit_checklist(): void
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
            'title' => 'Daily Walkthrough',
            'tasks' => [
                ['code' => 'entrance', 'label' => 'Check entrance'],
                ['code' => 'mess', 'label' => 'Inspect mess'],
            ],
            'active' => true,
            'created_by_user_id' => $warden->id,
        ]);

        (new ChecklistAutoCreateDailyJob())->handle(app(\App\Domain\Checklists\Repositories\ChecklistInstanceRepository::class));

        $instance = ChecklistInstance::firstOrFail();

        Sanctum::actingAs($warden, ['*']);

        $this->postJson("/api/v1/checklists/{$instance->id}/items/entrance", [
            'state' => 'Done',
        ])->assertOk();

        $this->postJson("/api/v1/checklists/{$instance->id}/items/mess", [
            'state' => 'NA',
        ])->assertOk();

        $this->postJson("/api/v1/checklists/{$instance->id}/submit")
            ->assertOk()
            ->assertJson(['status' => 'Submitted']);

        $instance->refresh();
        $this->assertSame('Submitted', $instance->status);
        $this->assertNotNull($instance->submitted_at);

        // Another user cannot mark items.
        $other = User::factory()->create(['tenant_id' => $tenant->id]);
        Sanctum::actingAs($other, ['*']);

        $this->postJson("/api/v1/checklists/{$instance->id}/items/entrance", [
            'state' => 'Done',
        ])->assertForbidden();

        // Cannot submit again once status is Submitted.
        Sanctum::actingAs($warden, ['*']);
        $this->postJson("/api/v1/checklists/{$instance->id}/submit")->assertStatus(403);
    }
}

