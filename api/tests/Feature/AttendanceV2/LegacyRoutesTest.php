<?php

namespace Tests\Feature\AttendanceV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_today_endpoint_returns_410_when_softkill_enabled(): void
    {
        config(['features.attendance_legacy_softkill' => true]);
        
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/attendance/today');
            
        $response->assertStatus(410)
            ->assertJson([
                'message' => 'This endpoint is deprecated',
                'location' => '/api/v1/attendance/session/today'
            ]);
    }

    public function test_legacy_history_endpoint_returns_410_when_softkill_enabled(): void
    {
        config(['features.attendance_legacy_softkill' => true]);
        
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/attendance/history');
            
        $response->assertStatus(410)
            ->assertJson([
                'message' => 'This endpoint is deprecated',
                'location' => '/api/v1/attendance/session/today'
            ]);
    }

    public function test_legacy_room_endpoint_returns_410_when_softkill_enabled(): void
    {
        config(['features.attendance_legacy_softkill' => true]);
        
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/attendance/room/1/1');
            
        $response->assertStatus(410)
            ->assertJson([
                'message' => 'This endpoint is deprecated',
                'location' => '/api/v1/attendance/session/today'
            ]);
    }

    public function test_legacy_mark_endpoint_returns_410_when_softkill_enabled(): void
    {
        config(['features.attendance_legacy_softkill' => true]);
        
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance/mark', []);
            
        $response->assertStatus(410)
            ->assertJson([
                'message' => 'This endpoint is deprecated',
                'location' => '/api/v1/attendance/session/today'
            ]);
    }

    public function test_legacy_submit_endpoint_returns_410_when_softkill_enabled(): void
    {
        config(['features.attendance_legacy_softkill' => true]);
        
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance/submit', []);
            
        $response->assertStatus(410)
            ->assertJson([
                'message' => 'This endpoint is deprecated',
                'location' => '/api/v1/attendance/session/today'
            ]);
    }

    public function test_legacy_endpoints_fallback_to_legacy_controller_when_softkill_disabled(): void
    {
        config(['features.attendance_legacy_softkill' => false]);
        
        $user = User::factory()->create();
        
        // These should not return 410 when softkill is disabled
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/attendance/today');
            
        // Should not be 410 (might be 404 or other error from legacy controller)
        $this->assertNotEquals(410, $response->getStatusCode());
    }
}
