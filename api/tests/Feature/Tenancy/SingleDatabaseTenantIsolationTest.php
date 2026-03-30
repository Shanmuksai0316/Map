<?php

namespace Tests\Feature\Tenancy;

use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Traits\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test Tenant Isolation with Single Shared Database
 * 
 * These tests verify that tenant isolation works correctly with
 * single shared database architecture using TenantScope.
 */
class SingleDatabaseTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private User $tenant1User;
    private User $tenant2User;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test tenants
        $this->tenant1 = Tenant::factory()->create(['code' => 'TENANT1']);
        $this->tenant2 = Tenant::factory()->create(['code' => 'TENANT2']);

        // Create test users
        $this->tenant1User = User::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'kind' => 'staff',
        ]);
        $this->tenant1User->assignRole('Campus Manager');

        $this->tenant2User = User::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'kind' => 'staff',
        ]);
        $this->tenant2User->assignRole('Campus Manager');

        // Create Super Admin
        $this->superAdmin = User::factory()->create([
            'tenant_id' => null, // Super Admin has no tenant
        ]);
        $this->superAdmin->assignRole('Super Admin');
    }

    /** @test */
    public function tenant_users_can_only_see_their_own_data(): void
    {
        // Create campuses for both tenants
        $tenant1Campus = Campus::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Tenant 1 Campus',
        ]);

        $tenant2Campus = Campus::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'name' => 'Tenant 2 Campus',
        ]);

        // Act as tenant1 user
        $this->actingAs($this->tenant1User);

        // Should only see tenant1's campus
        $campuses = Campus::all();
        $this->assertCount(1, $campuses);
        $this->assertEquals($tenant1Campus->id, $campuses->first()->id);
        $this->assertEquals($this->tenant1->id, $campuses->first()->tenant_id);
    }

    /** @test */
    public function super_admin_can_see_all_tenant_data(): void
    {
        // Create campuses for both tenants
        Campus::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Tenant 1 Campus',
        ]);

        Campus::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'name' => 'Tenant 2 Campus',
        ]);

        // Act as Super Admin
        $this->actingAs($this->superAdmin);

        // Should see all campuses (Super Admin bypasses TenantScope)
        $campuses = Campus::all();
        $this->assertCount(2, $campuses);
    }

    /** @test */
    public function tenant_scope_prevents_cross_tenant_access(): void
    {
        // Create hostel for tenant1
        $tenant1Hostel = Hostel::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Tenant 1 Hostel',
        ]);

        // Create hostel for tenant2
        $tenant2Hostel = Hostel::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'name' => 'Tenant 2 Hostel',
        ]);

        // Act as tenant1 user
        $this->actingAs($this->tenant1User);

        // Try to access tenant2's hostel by ID
        $hostel = Hostel::find($tenant2Hostel->id);
        $this->assertNull($hostel); // Should not be accessible
    }

    /** @test */
    public function tenant_id_is_automatically_set_on_creation(): void
    {
        // Act as tenant1 user
        $this->actingAs($this->tenant1User);

        // Create campus without explicitly setting tenant_id
        $campus = Campus::create([
            'name' => 'Auto Campus',
            'code' => 'AUTO',
        ]);

        // TenantScope should automatically set tenant_id
        $this->assertEquals($this->tenant1->id, $campus->tenant_id);
    }

    /** @test */
    public function queries_are_filtered_by_tenant_id(): void
    {
        // Create multiple records for each tenant
        Campus::factory()->count(3)->create(['tenant_id' => $this->tenant1->id]);
        Campus::factory()->count(2)->create(['tenant_id' => $this->tenant2->id]);

        // Act as tenant1 user
        $this->actingAs($this->tenant1User);

        // Should only see tenant1's campuses
        $campuses = Campus::all();
        $this->assertCount(3, $campuses);
        $this->assertTrue($campuses->every(fn($campus) => $campus->tenant_id === $this->tenant1->id));
    }

    /** @test */
    public function super_admin_can_bypass_tenant_scope(): void
    {
        // Create data for both tenants
        Campus::factory()->count(3)->create(['tenant_id' => $this->tenant1->id]);
        Campus::factory()->count(2)->create(['tenant_id' => $this->tenant2->id]);

        // Act as Super Admin
        $this->actingAs($this->superAdmin);

        // Without bypassing scope - should see all (Super Admin has null tenant_id)
        $allCampuses = Campus::all();
        $this->assertCount(5, $allCampuses);

        // With explicit bypass
        $explicitAll = Campus::withoutGlobalScope(TenantScope::class)->get();
        $this->assertCount(5, $explicitAll);
    }

    /** @test */
    public function relationships_respect_tenant_isolation(): void
    {
        // Create campus and hostel for tenant1
        $campus = Campus::factory()->create(['tenant_id' => $this->tenant1->id]);
        $hostel = Hostel::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'campus_id' => $campus->id,
        ]);

        // Act as tenant1 user
        $this->actingAs($this->tenant1User);

        // Should be able to access related hostel
        $campusHostels = $campus->hostels;
        $this->assertCount(1, $campusHostels);
        $this->assertEquals($hostel->id, $campusHostels->first()->id);
    }

    /** @test */
    public function update_operations_respect_tenant_isolation(): void
    {
        // Create campus for tenant1
        $campus = Campus::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Original Name',
        ]);

        // Create campus for tenant2 (different ID)
        $tenant2Campus = Campus::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'name' => 'Tenant 2 Campus',
        ]);

        // Act as tenant1 user
        $this->actingAs($this->tenant1User);

        // Try to update tenant2's campus - should not work
        $updated = Campus::where('id', $tenant2Campus->id)
            ->update(['name' => 'Hacked Name']);

        // Should return 0 (no rows updated due to TenantScope)
        $this->assertEquals(0, $updated);

        // Verify tenant2's campus is unchanged
        $tenant2Campus->refresh();
        $this->assertEquals('Tenant 2 Campus', $tenant2Campus->name);
    }

    /** @test */
    public function delete_operations_respect_tenant_isolation(): void
    {
        // Create campuses for both tenants
        $tenant1Campus = Campus::factory()->create(['tenant_id' => $this->tenant1->id]);
        $tenant2Campus = Campus::factory()->create(['tenant_id' => $this->tenant2->id]);

        // Act as tenant1 user
        $this->actingAs($this->tenant1User);

        // Try to delete tenant2's campus - should not work
        $deleted = Campus::where('id', $tenant2Campus->id)->delete();

        // Should return 0 (no rows deleted due to TenantScope)
        $this->assertEquals(0, $deleted);

        // Verify tenant2's campus still exists
        $this->assertDatabaseHas('campuses', [
            'id' => $tenant2Campus->id,
            'tenant_id' => $this->tenant2->id,
        ]);
    }
}

