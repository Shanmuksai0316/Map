<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Controllers\Admin\TenantImpersonationController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Tenant $tenant;
    protected User $tenantAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Rector', 'web');
        Role::findOrCreate('Campus Manager', 'web');
        
        // Create Super Admin
        $this->superAdmin = User::factory()->create(['tenant_id' => null]);
        $this->superAdmin->assignRole('Super Admin');
        
        // Create tenant with admin
        $this->tenant = Tenant::factory()->create(['status' => 'active']);
        $this->tenantAdmin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant Rector',
        ]);
        $this->tenantAdmin->assignRole('Rector');
    }

    public function test_super_admin_can_impersonate_tenant_admin(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->get(route('admin.impersonate', $this->tenant));
        
        $response->assertRedirect(route('filament.campus-manager.pages.dashboard'));
        $this->assertEquals($this->tenantAdmin->id, auth()->id());
        $this->assertEquals($this->superAdmin->id, session('impersonating_from'));
    }

    public function test_impersonation_creates_audit_log(): void
    {
        $this->actingAs($this->superAdmin);
        
        $this->get(route('admin.impersonate', $this->tenant));
        
        $this->assertDatabaseHas('tenant_impersonation_logs', [
            'super_admin_id' => (string) $this->superAdmin->id,
            'tenant_id' => $this->tenant->id,
            'impersonated_user_id' => (string) $this->tenantAdmin->id,
        ]);
        
        $log = DB::table('tenant_impersonation_logs')->latest('id')->first();
        $this->assertNotNull($log->started_at);
        $this->assertNull($log->ended_at);
        $this->assertNotNull($log->ip_address);
    }

    public function test_stop_impersonation_restores_super_admin(): void
    {
        // Start impersonation
        $this->actingAs($this->superAdmin);
        $this->get(route('admin.impersonate', $this->tenant));
        
        // Now acting as tenant admin
        $this->assertEquals($this->tenantAdmin->id, auth()->id());
        
        // Stop impersonation
        $response = $this->get(route('admin.stop-impersonation'));
        
        $response->assertRedirect(route('filament.admin.pages.dashboard'));
        $this->assertEquals($this->superAdmin->id, auth()->id());
        $this->assertNull(session('impersonating_from'));
    }

    public function test_stop_impersonation_updates_audit_log(): void
    {
        // Start and stop impersonation
        $this->actingAs($this->superAdmin);
        $this->get(route('admin.impersonate', $this->tenant));
        $this->get(route('admin.stop-impersonation'));
        
        $log = DB::table('tenant_impersonation_logs')
            ->where('super_admin_id', (string) $this->superAdmin->id)
            ->latest('id')
            ->first();
        
        $this->assertNotNull($log->ended_at);
    }

    public function test_non_super_admin_cannot_impersonate(): void
    {
        $regularUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $this->actingAs($regularUser);
        
        $response = $this->get(route('admin.impersonate', $this->tenant));
        
        $response->assertStatus(403);
    }

    public function test_impersonation_fails_if_no_tenant_admin_exists(): void
    {
        // Create tenant without admin users
        $emptyTenant = Tenant::factory()->create(['status' => 'active']);
        
        $this->actingAs($this->superAdmin);
        
        $response = $this->get(route('admin.impersonate', $emptyTenant));
        
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_cannot_nest_impersonation(): void
    {
        // Start first impersonation
        $this->actingAs($this->superAdmin);
        session(['impersonating_from' => $this->superAdmin->id]);
        
        $anotherTenant = Tenant::factory()->create();
        
        $response = $this->get(route('admin.impersonate', $anotherTenant));
        
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}

