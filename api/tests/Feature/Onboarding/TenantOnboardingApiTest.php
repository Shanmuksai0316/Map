<?php

namespace Tests\Feature\Onboarding;

use App\Enums\TenantStatus;
use App\Events\TenantActivated;
use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomBed;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantOnboardingApiTest extends TestCase
{
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles are already created by TestingBaselineSeeder
        // Just ensure Super Admin role exists for sanctum guard
        Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'sanctum',
        ]);

        $this->superAdmin = User::factory()->create([
            'tenant_id' => null,
            'kind' => 'superadmin',
            'phone' => '+911234567890',
            'name' => 'Super Admin',
        ]);
        $superAdminRole = Role::findByName('Super Admin', 'sanctum');
        $this->superAdmin->assignRole($superAdminRole);
    }

    public function test_super_admin_can_create_tenant_draft_idempotently(): void
    {
        $payload = [
            'name' => 'MAP Academy',
            'code' => 'MAP-ACAD',
            'campus_name' => 'Main Campus',
            'campus_address' => [
                'line1' => '123 Main St',
                'city' => 'Metropolis',
            ],
            'contact_email' => 'admin@mapacad.test',
            'contact_phone' => '+911112223334',
        ];

        $headers = [
            'Idempotency-Key' => 'draft-tenant-123',
        ];

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->withHeaders($headers)
            ->postJson('/api/v1/tenants', $payload);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'code', 'status']]);
        $this->assertDatabaseHas('tenants', [
            'code' => 'MAP-ACAD',
            'status' => TenantStatus::PROVISIONING->value,
        ]);

        // Baseline seeder creates 1 tenant (MAP-TEST), so we expect 2 total (1 baseline + 1 from test)
        $this->assertEquals(2, Tenant::count(), 'Tenant should be created exactly once (baseline + test tenant)');

        // Repeat same request with same idempotency key - should replay cached response
        $secondResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->withHeaders($headers)
            ->postJson('/api/v1/tenants', $payload);

        $secondResponse->assertCreated()
            ->assertHeader('X-Idempotency-Replayed', 'true')
            ->assertJsonPath('data.code', 'MAP-ACAD');

        // Should still be 2 (no duplicate created)
        $this->assertEquals(2, Tenant::count(), 'No duplicate tenants should be created');
    }

    public function test_update_wizard_persists_data_for_provisioning_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'code' => 'MAP-WIZ',
            'status' => TenantStatus::PROVISIONING,
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/tenants/{$tenant->id}/wizard", [
                'step' => 'hostels',
                'data' => [
                    [
                        'name' => 'Hostel 1',
                        'code' => 'H1',
                        'curfew' => '22:00',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.saved', true)
            ->assertJsonPath('data.step', 'hostels');

        $this->assertSame(
            'Hostel 1',
            $tenant->fresh()->data['wizard']['hostels'][0]['name'] ?? null
        );
    }

    public function test_activate_returns_preflight_errors_when_requirements_missing(): void
    {
        $tenant = Tenant::factory()->create([
            'code' => 'MAP-PREFLIGHT',
            'status' => TenantStatus::PROVISIONING,
            'data' => ['wizard' => []],
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/tenants/{$tenant->id}/activate");

        $response->assertStatus(422)
            ->assertJsonPath('code', 'onboarding/preflight_failed')
            ->assertJsonStructure(['errors']);

        $this->assertEquals(TenantStatus::PROVISIONING, $tenant->fresh()->status);
    }

    public function test_activate_succeeds_after_preflight_checks_pass(): void
    {
        Event::fake([TenantActivated::class]);

        $tenant = Tenant::factory()->create([
            'code' => 'MAP-READY',
            'status' => TenantStatus::PROVISIONING,
            'data' => [],
        ]);

        $campus = Campus::create([
            'tenant_id' => $tenant->id,
            'code' => 'CMP-1',
            'name' => 'Primary Campus',
            'address' => ['line1' => '123 Campus Rd'],
        ]);

        $hostel = Hostel::create([
            'tenant_id' => $tenant->id,
            'campus_id' => $campus->id,
            'code' => 'HOS-1',
            'name' => 'Central Hostel',
            'gender_mode' => 'co-ed',
            'curfew_time' => '22:00:00',
        ]);

        $room = Room::create([
            'tenant_id' => $tenant->id,
            'campus_id' => $campus->id,
            'hostel_id' => $hostel->id,
            'number' => '101',
            'capacity' => 2,
            'is_active' => true,
        ]);

        RoomBed::create([
            'tenant_id' => $tenant->id,
            'room_id' => $room->id,
            'hostel_id' => $hostel->id,
            'code' => 'A',
            'status' => 'available',
        ]);

        $campusManager = User::factory()->create([
            'tenant_id' => $tenant->id,
            'phone' => '+911000000001',
            'name' => 'Campus Manager',
            'kind' => 'campusmanager',
        ]);
        $campusManager->assignRole('Campus Manager');

        $roles = [
            'Rector' => '+911000000002',
            'Warden' => '+911000000003',
            'Guard' => '+911000000004',
            'HK Supervisor' => '+911000000005',
            'RM Supervisor' => '+911000000006',
        ];

        $assignedUsers = [];
        foreach ($roles as $roleName => $phone) {
            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'phone' => $phone,
                'name' => $roleName,
            ]);
            $user->assignRole($roleName);
            $assignedUsers[$roleName] = $user;

            DB::table('staff_assignments')->insert([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'hostel_id' => $hostel->id,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $collegeMgmt = User::factory()->create([
            'tenant_id' => $tenant->id,
            'phone' => '+911000000007',
            'name' => 'College Manager',
        ]);
        $collegeMgmt->assignRole('College Management');

        $tenant->update([
            'data' => [
                'wizard' => [
                    'rector_user_id' => $assignedUsers['Rector']->id,
                    'college_mgmt_user_id' => $collegeMgmt->id,
                    'hostels' => [
                        [
                            'id' => $hostel->id,
                            'roles_na' => ['laundry_manager', 'sports_manager'],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->withHeaders(['Idempotency-Key' => 'activate-ready'])
            ->postJson("/api/v1/tenants/{$tenant->id}/activate");

        $response->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertEquals(TenantStatus::ACTIVE, $tenant->fresh()->status);

        Event::assertDispatched(TenantActivated::class, function (TenantActivated $event) use ($tenant) {
            return $event->tenant->id === $tenant->id;
        });
    }
}
