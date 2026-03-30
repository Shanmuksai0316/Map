<?php

namespace Tests\Feature\Api\V1\CampusManager;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $campusManager;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->campusManager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'campus_manager',
        ]);
    }

    public function test_campus_manager_can_list_staff(): void
    {
        Sanctum::actingAs($this->campusManager);

        User::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'warden',
        ]);

        $response = $this->getJson('/api/v1/campus-manager/staff');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'role'],
                ],
            ]);
    }

    public function test_campus_manager_can_view_staff_details(): void
    {
        Sanctum::actingAs($this->campusManager);

        $staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'warden',
        ]);

        $response = $this->getJson("/api/v1/campus-manager/staff/{$staff->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $staff->id)
            ->assertJsonPath('data.name', $staff->name);
    }

    public function test_campus_manager_can_create_staff(): void
    {
        Sanctum::actingAs($this->campusManager);

        $staffData = [
            'name' => 'New Staff Member',
            'email' => 'newstaff@example.com',
            'password' => 'password123',
            'role' => 'guard',
            'phone' => '9876543210',
        ];

        $response = $this->postJson('/api/v1/campus-manager/staff', $staffData);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New Staff Member')
            ->assertJsonPath('data.role', 'guard');

        $this->assertDatabaseHas('users', [
            'email' => 'newstaff@example.com',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_campus_manager_can_update_staff(): void
    {
        Sanctum::actingAs($this->campusManager);

        $staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'warden',
        ]);

        $response = $this->putJson("/api/v1/campus-manager/staff/{$staff->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_campus_manager_can_delete_staff(): void
    {
        Sanctum::actingAs($this->campusManager);

        $staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'warden',
        ]);

        $response = $this->deleteJson("/api/v1/campus-manager/staff/{$staff->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $staff->id]);
    }

    public function test_non_campus_manager_cannot_access_staff_endpoints(): void
    {
        $warden = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'warden',
        ]);

        Sanctum::actingAs($warden);

        $response = $this->getJson('/api/v1/campus-manager/staff');

        $response->assertForbidden();
    }
}

