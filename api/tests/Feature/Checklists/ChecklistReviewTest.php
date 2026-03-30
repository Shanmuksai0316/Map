<?php

namespace Tests\Feature\Checklists;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChecklistReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_campus_manager_can_approve_submitted_checklist(): void
    {
        config(['features.checklists_module' => true]);

        // Create roles
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Campus Manager', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Campus Manager', 'guard_name' => 'sanctum']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'sanctum']);

        $tenant = Tenant::factory()->create();
        $campusManager = User::factory()->create(['tenant_id' => $tenant->id]);
        $campusManager->assignRole('Campus Manager');

        $assignee = User::factory()->create(['tenant_id' => $tenant->id]);
        $assignee->assignRole('Warden');

        $template = ChecklistTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'role' => 'Warden',
            'title' => 'Daily Check',
            'tasks' => [['code' => 'task1', 'label' => 'Task 1']],
            'active' => true,
            'created_by_user_id' => $assignee->id,
        ]);

        $instance = ChecklistInstance::query()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'date' => now()->toDateString(),
            'shift' => 'Daily',
            'role' => 'Warden',
            'assignee_user_id' => $assignee->id,
            'status' => 'Submitted',
            'review_status' => 'Pending',
            'total_tasks' => 1,
            'completed_tasks' => 1,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($campusManager, ['*']);

        $response = $this->postJson("/api/v1/checklists/{$instance->id}/approve");

        $response->assertOk()
            ->assertJson([
                'review_status' => 'Approved',
                'manager_user_id' => $campusManager->id,
            ])
            ->assertJsonStructure(['reviewed_at']);

        $this->assertDatabaseHas('checklist_instances', [
            'id' => $instance->id,
            'review_status' => 'Approved',
            'manager_user_id' => $campusManager->id,
        ]);
    }

    public function test_campus_manager_can_send_back_submitted_checklist(): void
    {
        config(['features.checklists_module' => true]);

        // Create roles
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Campus Manager', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Campus Manager', 'guard_name' => 'sanctum']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'sanctum']);

        $tenant = Tenant::factory()->create();
        $campusManager = User::factory()->create(['tenant_id' => $tenant->id]);
        $campusManager->assignRole('Campus Manager');

        $assignee = User::factory()->create(['tenant_id' => $tenant->id]);
        $assignee->assignRole('Warden');

        $template = ChecklistTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'role' => 'Warden',
            'title' => 'Daily Check',
            'tasks' => [['code' => 'task1', 'label' => 'Task 1']],
            'active' => true,
            'created_by_user_id' => $assignee->id,
        ]);

        $instance = ChecklistInstance::query()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'date' => now()->toDateString(),
            'shift' => 'Daily',
            'role' => 'Warden',
            'assignee_user_id' => $assignee->id,
            'status' => 'Submitted',
            'review_status' => 'Pending',
            'total_tasks' => 1,
            'completed_tasks' => 1,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($campusManager, ['*']);

        $response = $this->postJson("/api/v1/checklists/{$instance->id}/send-back", [
            'note' => 'Please recheck the entrance.',
        ]);

        $response->assertOk()
            ->assertJson([
                'review_status' => 'SentBack',
                'manager_note' => 'Please recheck the entrance.',
            ])
            ->assertJsonStructure(['reviewed_at']);

        $this->assertDatabaseHas('checklist_instances', [
            'id' => $instance->id,
            'review_status' => 'SentBack',
            'manager_user_id' => $campusManager->id,
            'manager_note' => 'Please recheck the entrance.',
        ]);
    }

    public function test_assignee_cannot_approve_their_own_checklist(): void
    {
        config(['features.checklists_module' => true]);

        // Create roles
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'sanctum']);

        $tenant = Tenant::factory()->create();
        $assignee = User::factory()->create(['tenant_id' => $tenant->id]);
        $assignee->assignRole('Warden');

        $template = ChecklistTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'role' => 'Warden',
            'title' => 'Daily Check',
            'tasks' => [['code' => 'task1', 'label' => 'Task 1']],
            'active' => true,
            'created_by_user_id' => $assignee->id,
        ]);

        $instance = ChecklistInstance::query()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'date' => now()->toDateString(),
            'shift' => 'Daily',
            'role' => 'Warden',
            'assignee_user_id' => $assignee->id,
            'status' => 'Submitted',
            'review_status' => 'Pending',
            'total_tasks' => 1,
            'completed_tasks' => 1,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($assignee, ['*']);

        $response = $this->postJson("/api/v1/checklists/{$instance->id}/approve");

        $response->assertForbidden();
    }

    public function test_rector_cannot_approve_checklist(): void
    {
        config(['features.checklists_module' => true]);

        // Create roles
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Rector', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Rector', 'guard_name' => 'sanctum']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'sanctum']);

        $tenant = Tenant::factory()->create();
        $rector = User::factory()->create(['tenant_id' => $tenant->id]);
        $rector->assignRole('Rector');

        $assignee = User::factory()->create(['tenant_id' => $tenant->id]);
        $assignee->assignRole('Warden');

        $template = ChecklistTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'role' => 'Warden',
            'title' => 'Daily Check',
            'tasks' => [['code' => 'task1', 'label' => 'Task 1']],
            'active' => true,
            'created_by_user_id' => $assignee->id,
        ]);

        $instance = ChecklistInstance::query()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'date' => now()->toDateString(),
            'shift' => 'Daily',
            'role' => 'Warden',
            'assignee_user_id' => $assignee->id,
            'status' => 'Submitted',
            'review_status' => 'Pending',
            'total_tasks' => 1,
            'completed_tasks' => 1,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($rector, ['*']);

        $response = $this->postJson("/api/v1/checklists/{$instance->id}/approve");

        $response->assertForbidden();
    }
}
