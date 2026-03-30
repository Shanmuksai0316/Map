<?php

namespace Tests\Feature\AttendanceV2;

use App\Domain\Attendance\Models\AttendanceSessionV2;
use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TodayEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_endpoint_returns_session_data(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $session = AttendanceSessionV2::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_date' => now('Asia/Kolkata')->toDateString(),
            'status' => 'active',
            'scheduled_at' => now(),
            'metadata' => [
                'window' => [
                    'start' => now('Asia/Kolkata')->subMinutes(30)->toISOString(),
                    'end' => now('Asia/Kolkata')->addHours(1)->toISOString(),
                ]
            ],
        ]);
        
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/attendance/session/today');
            
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'hostel_id',
                    'date',
                    'window' => ['start', 'end'],
                    'status',
                    'counts' => ['total', 'present', 'absent', 'unmarked'],
                ]
            ]);
            
        $response->assertJson([
            'data' => [
                'id' => $session->id,
                'hostel_id' => $hostel->id,
                'date' => $session->session_date,
                'status' => 'active',
            ]
        ]);
    }

    public function test_today_endpoint_returns_404_when_no_session(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/attendance/session/today');
            
        $response->assertStatus(200)
            ->assertJson(['data' => null]);
    }

    public function test_today_endpoint_returns_404_when_no_hostel(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/attendance/session/today');
            
        $response->assertStatus(404)
            ->assertJson(['error' => 'No hostel found']);
    }

    public function test_today_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/attendance/session/today');
        $response->assertStatus(401);
    }

    public function test_today_endpoint_respects_hostel_scope(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        $hostel1 = Hostel::factory()->create(['tenant_id' => $tenant1->id]);
        $hostel2 = Hostel::factory()->create(['tenant_id' => $tenant2->id]);
        
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        
        // Create session for hostel1
        AttendanceSessionV2::create([
            'tenant_id' => $tenant1->id,
            'hostel_id' => $hostel1->id,
            'session_date' => now('Asia/Kolkata')->toDateString(),
            'status' => 'active',
            'scheduled_at' => now(),
        ]);
        
        // User2 should not see tenant1's session (user2 has access to hostel2 but no session exists for hostel2)
        $response = $this->actingAs($user2, 'sanctum')
            ->getJson('/api/v1/attendance/session/today');
            
        $response->assertStatus(200)
            ->assertJson(['data' => null]);
    }
}
