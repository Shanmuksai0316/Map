<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seedTestBootstrap = false;
    protected bool $ensureDefaultTestingTenant = false;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_tenant_starts_in_provisioning_status(): void
    {
        $tenant = Tenant::factory()->create();
        
        $this->assertEquals(TenantStatus::PROVISIONING, $tenant->status);
    }

    public function test_tenant_can_be_suspended(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        
        $tenant->suspend('Non-payment');
        
        $this->assertEquals(TenantStatus::SUSPENDED, $tenant->fresh()->status);
        $this->assertNotNull($tenant->fresh()->suspended_at);
        $this->assertEquals('Non-payment', $tenant->fresh()->suspended_reason);
    }

    public function test_tenant_can_be_archived(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        
        $tenant->archive();
        
        $this->assertEquals(TenantStatus::ARCHIVED, $tenant->fresh()->status);
        $this->assertNotNull($tenant->fresh()->archived_at);
    }

    public function test_tenant_can_be_reactivated_from_suspended(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspended_reason' => 'Non-payment',
        ]);
        
        $tenant->reactivate();
        
        $this->assertEquals(TenantStatus::ACTIVE, $tenant->fresh()->status);
        $this->assertNull($tenant->fresh()->suspended_at);
        $this->assertNull($tenant->fresh()->suspended_reason);
    }

    public function test_tenant_can_be_reactivated_from_archived(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => 'archived',
            'archived_at' => now(),
        ]);
        
        $tenant->reactivate();
        
        $this->assertEquals(TenantStatus::ACTIVE, $tenant->fresh()->status);
        $this->assertNull($tenant->fresh()->archived_at);
    }

    public function test_active_tenant_can_access_system(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        
        $this->assertTrue($tenant->canAccess());
    }

    public function test_provisioning_tenant_can_access_system(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'provisioning']);
        
        $this->assertTrue($tenant->canAccess());
    }

    public function test_suspended_tenant_cannot_access_system(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'suspended']);
        
        $this->assertFalse($tenant->canAccess());
    }

    public function test_archived_tenant_cannot_access_system(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'archived']);
        
        $this->assertFalse($tenant->canAccess());
    }

    public function test_subscription_expiry_check_works(): void
    {
        $expiredTenant = Tenant::factory()->create([
            'subscription_ends_at' => now()->subDays(1),
        ]);
        
        $this->assertTrue($expiredTenant->isSubscriptionExpired());
        
        $activeTenant = Tenant::factory()->create([
            'subscription_ends_at' => now()->addDays(30),
        ]);
        
        $this->assertFalse($activeTenant->isSubscriptionExpired());
    }

    public function test_tenant_scopes_work_correctly(): void
    {
        Tenant::factory()->create(['status' => 'active']);
        Tenant::factory()->create(['status' => 'suspended']);
        Tenant::factory()->create(['status' => 'archived']);
        Tenant::factory()->create(['status' => 'provisioning']);
        
        $this->assertEquals(1, Tenant::active()->count());
        $this->assertEquals(1, Tenant::suspended()->count());
        $this->assertEquals(1, Tenant::archived()->count());
        $this->assertEquals(1, Tenant::provisioning()->count());
    }

    public function test_tenant_soft_delete_works(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantId = $tenant->id;
        
        $tenant->delete();
        
        // Soft deleted - still in database
        $this->assertSoftDeleted('tenants', ['id' => $tenantId]);
        
        // Can be restored
        $tenant->restore();
        $this->assertDatabaseHas('tenants', ['id' => $tenantId, 'deleted_at' => null]);
    }
}

