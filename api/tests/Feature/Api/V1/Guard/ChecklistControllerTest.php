<?php

namespace Tests\Feature\Api\V1\Guard;

use App\Models\User;
use App\Models\Tenant;
use App\Models\ChecklistInstance;
use App\Models\ChecklistTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChecklistControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $guard;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->guard = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'guard',
        ]);
    }

    public function test_guard_can_fetch_today_checklist(): void
    {
        Sanctum::actingAs($this->guard);

        $checklist = ChecklistInstance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->guard->id,
            'date' => now()->toDateString(),
        ]);

        ChecklistTask::factory()->count(5)->create([
            'checklist_instance_id' => $checklist->id,
        ]);

        $response = $this->getJson('/api/v1/guard/checklist/today');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'date',
                    'tasks' => [
                        '*' => ['id', 'title', 'description', 'is_completed'],
                    ],
                ],
            ]);
    }

    public function test_guard_can_complete_task(): void
    {
        Sanctum::actingAs($this->guard);

        $checklist = ChecklistInstance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->guard->id,
            'date' => now()->toDateString(),
        ]);

        $task = ChecklistTask::factory()->create([
            'checklist_instance_id' => $checklist->id,
            'is_completed' => false,
            'requires_photo' => false,
        ]);

        $response = $this->postJson("/api/v1/guard/checklist/complete-task/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_completed', true);

        $this->assertDatabaseHas('checklist_tasks', [
            'id' => $task->id,
            'is_completed' => true,
        ]);
    }

    public function test_guard_cannot_complete_task_requiring_photo_without_photo(): void
    {
        Sanctum::actingAs($this->guard);

        $checklist = ChecklistInstance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->guard->id,
            'date' => now()->toDateString(),
        ]);

        $task = ChecklistTask::factory()->create([
            'checklist_instance_id' => $checklist->id,
            'is_completed' => false,
            'requires_photo' => true,
            'photo_url' => null,
        ]);

        $response = $this->postJson("/api/v1/guard/checklist/complete-task/{$task->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Photo is required for this task');
    }

    public function test_guard_can_upload_photo_for_task(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->guard);

        $checklist = ChecklistInstance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->guard->id,
            'date' => now()->toDateString(),
        ]);

        $task = ChecklistTask::factory()->create([
            'checklist_instance_id' => $checklist->id,
            'requires_photo' => true,
        ]);

        $file = UploadedFile::fake()->image('checklist-photo.jpg');

        $response = $this->postJson("/api/v1/guard/checklist/upload-photo/{$task->id}", [
            'photo' => $file,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['photo_url'],
            ]);

        $this->assertNotNull($task->fresh()->photo_url);
    }

    public function test_non_guard_cannot_access_checklist_endpoints(): void
    {
        $warden = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'warden',
        ]);

        Sanctum::actingAs($warden);

        $response = $this->getJson('/api/v1/guard/checklist/today');

        $response->assertForbidden();
    }
}

