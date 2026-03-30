<?php

namespace Tests\Feature\CriticalFlows;

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

/**
 * Full Tenant Onboarding Wizard E2E Test
 * 
 * Tests the complete 6-step wizard flow:
 * 1. Tenant Details (create draft)
 * 2. Hostel Setup
 * 3. Staff Assignment  
 * 4. Room Configuration
 * 5. Amenities
 * 6. Activation
 */
class TenantOnboardingFullFlowTest extends TestCase
{
    private User $superAdmin;
    private string $idempotencyKey;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'sanctum');
        Role::findOrCreate('Campus Manager', 'sanctum');
        Role::findOrCreate('Rector', 'sanctum');
        Role::findOrCreate('Warden', 'sanctum');
        Role::findOrCreate('Guard', 'sanctum');
        Role::findOrCreate('HK Supervisor', 'sanctum');
        Role::findOrCreate('RM Supervisor', 'sanctum');
        Role::findOrCreate('College Management', 'sanctum');

        $this->superAdmin = User::factory()->create([
            'tenant_id' => null,
            'kind' => 'superadmin',
            'phone' => '+911234567890',
            'name' => 'Super Admin',
        ]);
        $this->superAdmin->assignRole(Role::findByName('Super Admin', 'sanctum'));
        
        $this->idempotencyKey = 'full-onboarding-' . uniqid();
    }

    /**
     * FULL E2E TEST: Complete tenant onboarding flow
     */
    public function test_complete_tenant_onboarding_wizard_end_to_end(): void
    {
        Event::fake([TenantActivated::class]);

        // ============================================
        // STEP 1: Create Tenant Draft
        // ============================================
        $tenantPayload = [
            'name' => 'Full Flow Test Academy',
            'code' => 'FFA-' . substr(uniqid(), 0, 4),
            'campus_name' => 'Main Campus',
            'campus_address' => [
                'line1' => '123 Main St',
                'city' => 'Metropolis',
                'state' => 'Delhi',
                'pincode' => '110001',
            ],
            'contact_email' => 'admin@fullflowtest.test',
            'contact_phone' => '+911112223334',
        ];

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->withHeaders(['Idempotency-Key' => $this->idempotencyKey . '-step1'])
            ->postJson('/api/v1/tenants', $tenantPayload);

        $response->assertCreated();
        $tenantId = $response->json('data.id');
        $tenantCode = $response->json('data.code');
        
        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'status' => TenantStatus::PROVISIONING->value,
        ]);

        // ============================================
        // STEP 2: Hostel Setup (via wizard update)
        // ============================================
        $hostelData = [
            'step' => 'hostels',
            'data' => [
                [
                    'name' => 'Boys Hostel A',
                    'code' => 'BHA',
                    'gender_mode' => 'male',
                    'curfew' => '22:00',
                    'capacity' => 100,
                ],
                [
                    'name' => 'Girls Hostel A',
                    'code' => 'GHA',
                    'gender_mode' => 'female',
                    'curfew' => '21:00',
                    'capacity' => 100,
                ],
            ],
        ];

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/tenants/{$tenantId}/wizard", $hostelData);

        $response->assertOk();

        // ============================================
        // STEP 3: Create actual hostels in database
        // ============================================
        $tenant = Tenant::find($tenantId);
        $campus = Campus::create([
            'tenant_id' => $tenant->id,
            'code' => 'CMP-1',
            'name' => 'Primary Campus',
            'address' => $tenantPayload['campus_address'],
        ]);

        $hostels = [];
        foreach ($hostelData['data'] as $hostelInfo) {
            $hostels[] = Hostel::create([
                'tenant_id' => $tenant->id,
                'campus_id' => $campus->id,
                'code' => $hostelInfo['code'],
                'name' => $hostelInfo['name'],
                'gender_mode' => $hostelInfo['gender_mode'],
                'curfew_time' => $hostelInfo['curfew'] . ':00',
            ]);
        }

        // ============================================
        // STEP 4: Room Configuration
        // ============================================
        $rooms = [];
        foreach ($hostels as $hostel) {
            for ($i = 1; $i <= 5; $i++) {
                $room = Room::create([
                    'tenant_id' => $tenant->id,
                    'campus_id' => $campus->id,
                    'hostel_id' => $hostel->id,
                    'number' => $hostel->code . '-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'capacity' => 2,
                    'is_active' => true,
                ]);
                $rooms[] = $room;

                // Create beds for each room
                foreach (['A', 'B'] as $bedCode) {
                    RoomBed::create([
                        'tenant_id' => $tenant->id,
                        'room_id' => $room->id,
                        'hostel_id' => $hostel->id,
                        'code' => $bedCode,
                        'status' => 'available',
                    ]);
                }
            }
        }

        $this->assertEquals(10, Room::where('tenant_id', $tenant->id)->count());
        $this->assertEquals(20, RoomBed::where('tenant_id', $tenant->id)->count());

        // ============================================
        // STEP 5: Staff Assignment
        // ============================================
        $staffAssignments = [];

        // Create Campus Manager
        $campusManager = User::factory()->create([
            'tenant_id' => $tenant->id,
            'phone' => '+911000000001',
            'name' => 'Campus Manager Test',
            'kind' => 'campusmanager',
        ]);
        $campusManager->assignRole('Campus Manager');

        // Create staff for each hostel
        $roles = [
            'Rector' => '+911000000002',
            'Warden' => '+911000000003',
            'Guard' => '+911000000004',
            'HK Supervisor' => '+911000000005',
            'RM Supervisor' => '+911000000006',
        ];

        foreach ($hostels as $hostel) {
            foreach ($roles as $roleName => $basePhone) {
                $user = User::factory()->create([
                    'tenant_id' => $tenant->id,
                    'phone' => $basePhone . $hostel->id,
                    'name' => "{$roleName} - {$hostel->name}",
                ]);
                $user->assignRole($roleName);
                
                DB::table('staff_assignments')->insert([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'hostel_id' => $hostel->id,
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $staffAssignments[$roleName][$hostel->id] = $user;
            }
        }

        // Create College Management user
        $collegeMgmt = User::factory()->create([
            'tenant_id' => $tenant->id,
            'phone' => '+911000000007',
            'name' => 'College Management Test',
        ]);
        $collegeMgmt->assignRole('College Management');

        // ============================================
        // STEP 6: Update wizard data with staff references
        // ============================================
        $wizardData = [
            'wizard' => [
                'rector_user_id' => $staffAssignments['Rector'][$hostels[0]->id]->id,
                'college_mgmt_user_id' => $collegeMgmt->id,
                'campus_manager_id' => $campusManager->id,
                'hostels' => array_map(function ($hostel) {
                    return [
                        'id' => $hostel->id,
                        'roles_na' => ['laundry_manager', 'sports_manager'],
                    ];
                }, $hostels),
            ],
        ];

        $tenant->update(['data' => $wizardData]);

        // ============================================
        // STEP 7: Activation
        // ============================================
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->withHeaders(['Idempotency-Key' => $this->idempotencyKey . '-activate'])
            ->postJson("/api/v1/tenants/{$tenant->id}/activate");

        $response->assertOk();
        $this->assertEquals('active', $response->json('data.status'));

        // Verify final state
        $tenant->refresh();
        $this->assertEquals(TenantStatus::ACTIVE, $tenant->status);

        // Verify TenantActivated event was dispatched
        Event::assertDispatched(TenantActivated::class, function (TenantActivated $event) use ($tenant) {
            return $event->tenant->id === $tenant->id;
        });

        // ============================================
        // FINAL VERIFICATION: All data is in place
        // ============================================
        $this->assertDatabaseHas('campuses', [
            'tenant_id' => $tenant->id,
            'name' => 'Primary Campus',
        ]);

        $this->assertEquals(2, Hostel::where('tenant_id', $tenant->id)->count());
        $this->assertEquals(10, Room::where('tenant_id', $tenant->id)->count());
        $this->assertEquals(20, RoomBed::where('tenant_id', $tenant->id)->count());
        
        // Verify all staff assignments exist
        $staffCount = DB::table('staff_assignments')
            ->where('tenant_id', $tenant->id)
            ->count();
        $this->assertEquals(10, $staffCount); // 5 roles × 2 hostels
    }

    /**
     * Test idempotency of activation endpoint
     */
    public function test_activation_is_idempotent(): void
    {
        Event::fake([TenantActivated::class]);

        // Create a fully configured tenant
        $tenant = $this->createFullyConfiguredTenant();

        // First activation
        $response1 = $this->actingAs($this->superAdmin, 'sanctum')
            ->withHeaders(['Idempotency-Key' => 'activate-idempotent-test'])
            ->postJson("/api/v1/tenants/{$tenant->id}/activate");

        $response1->assertOk();

        // Second activation with same idempotency key should replay
        $response2 = $this->actingAs($this->superAdmin, 'sanctum')
            ->withHeaders(['Idempotency-Key' => 'activate-idempotent-test'])
            ->postJson("/api/v1/tenants/{$tenant->id}/activate");

        $response2->assertOk();
        $response2->assertHeader('X-Idempotency-Replayed', 'true');

        // Event should only be dispatched once
        Event::assertDispatchedTimes(TenantActivated::class, 1);
    }

    /**
     * Test activation fails without required staff
     */
    public function test_activation_fails_without_required_staff(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => TenantStatus::PROVISIONING,
            'data' => ['wizard' => []],
        ]);

        // Create campus and hostel but NO staff
        $campus = Campus::create([
            'tenant_id' => $tenant->id,
            'code' => 'CMP-1',
            'name' => 'Test Campus',
            'address' => ['line1' => '123 Test St'],
        ]);

        Hostel::create([
            'tenant_id' => $tenant->id,
            'campus_id' => $campus->id,
            'code' => 'H1',
            'name' => 'Test Hostel',
            'gender_mode' => 'co-ed',
            'curfew_time' => '22:00:00',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/tenants/{$tenant->id}/activate");

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'onboarding/preflight_failed');
    }

    /**
     * Helper: Create a fully configured tenant ready for activation
     */
    private function createFullyConfiguredTenant(): Tenant
    {
        $tenant = Tenant::factory()->create([
            'code' => 'FULL-' . substr(uniqid(), 0, 4),
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

        // Create required staff
        $campusManager = User::factory()->create([
            'tenant_id' => $tenant->id,
            'phone' => '+91' . rand(1000000000, 9999999999),
            'name' => 'Campus Manager',
            'kind' => 'campusmanager',
        ]);
        $campusManager->assignRole('Campus Manager');

        $roles = ['Rector', 'Warden', 'Guard', 'HK Supervisor', 'RM Supervisor'];
        $assignedUsers = [];

        foreach ($roles as $roleName) {
            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'phone' => '+91' . rand(1000000000, 9999999999),
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
            'phone' => '+91' . rand(1000000000, 9999999999),
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

        return $tenant;
    }
}


