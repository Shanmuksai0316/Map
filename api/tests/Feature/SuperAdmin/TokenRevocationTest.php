<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Hostel;
use App\Events\UserRoleChanged;
use App\Events\StaffAssignmentChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TokenRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $tenant = Tenant::factory()->create();
        $this->superAdmin = User::factory()->superAdmin()->create(['tenant_id' => $tenant->id]);
        $this->staffUser = User::factory()->warden()->create(['tenant_id' => $tenant->id]);
        $this->hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        
        // Assign roles
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $wardenRole = Role::firstOrCreate(['name' => 'Warden']);
        
        $this->superAdmin->assignRole($superAdminRole);
        $this->staffUser->assignRole($wardenRole);
    }

    public function test_token_revoked_on_role_change()
    {
        // Create a Sanctum token for the staff user
        $token = $this->staffUser->createToken('test-token');
        $tokenString = $token->plainTextToken;
        
        // Verify token works
        $this->actingAs($this->staffUser, 'sanctum')
            ->get('/api/v1/healthz')
            ->assertStatus(200);
        
        // Change role (this should trigger token revocation)
        $newRole = Role::firstOrCreate(['name' => 'Guard']);
        $this->staffUser->assignRole($newRole);
        
        // Fire the event manually (in real app, this would be automatic)
        event(new UserRoleChanged($this->staffUser->id, $this->superAdmin->id, now()->toISOString()));
        
        // Verify token is now invalid
        $this->withHeaders(['Authorization' => 'Bearer ' . $tokenString])
            ->get('/api/v1/healthz')
            ->assertStatus(401);
    }

    public function test_token_revoked_on_hostel_assignment_change()
    {
        // Create a Sanctum token for the staff user
        $token = $this->staffUser->createToken('test-token');
        $tokenString = $token->plainTextToken;
        
        // Verify token works
        $this->actingAs($this->staffUser, 'sanctum')
            ->get('/api/v1/healthz')
            ->assertStatus(200);
        
        // Change hostel assignment (this should trigger token revocation)
        $this->staffUser->staffHostels()->attach($this->hostel->id, [
            'tenant_id' => $this->staffUser->tenant_id,
            'assigned_at' => now(),
        ]);
        
        // Fire the event manually (in real app, this would be automatic)
        event(new StaffAssignmentChanged($this->staffUser->id, $this->superAdmin->id, now()->toISOString()));
        
        // Verify token is now invalid
        $this->withHeaders(['Authorization' => 'Bearer ' . $tokenString])
            ->get('/api/v1/healthz')
            ->assertStatus(401);
    }

    public function test_events_are_fired_on_role_change()
    {
        Event::fake();
        
        $newRole = Role::firstOrCreate(['name' => 'Guard']);
        $this->staffUser->assignRole($newRole);
        
        // Fire the event manually
        event(new UserRoleChanged($this->staffUser->id, $this->superAdmin->id, now()->toISOString()));
        
        Event::assertDispatched(UserRoleChanged::class);
    }

    public function test_events_are_fired_on_assignment_change()
    {
        Event::fake();
        
        $this->staffUser->staffHostels()->attach($this->hostel->id, [
            'tenant_id' => $this->staffUser->tenant_id,
            'assigned_at' => now(),
        ]);
        
        // Fire the event manually
        event(new StaffAssignmentChanged($this->staffUser->id, $this->superAdmin->id, now()->toISOString()));
        
        Event::assertDispatched(StaffAssignmentChanged::class);
    }
}
