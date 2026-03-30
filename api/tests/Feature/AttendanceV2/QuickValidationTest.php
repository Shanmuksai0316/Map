<?php

namespace Tests\Feature\AttendanceV2;

use App\Domain\Attendance\Models\AttendanceSessionV2;
use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_endpoint_returns_prd_compliant_payload(): void
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
                    'counts' => ['total', 'present', 'absent', 'unmarked']
                ]
            ])
            ->assertJsonPath('data.status', 'active') // lowercase
            ->assertJsonPath('data.id', $session->id)
            ->assertJsonPath('data.hostel_id', $hostel->id);
    }

    public function test_mark_endpoint_rejects_leave_in_payload(): void
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
        ]);
        
        $payload = [
            'student_id' => 999,
            'mark' => 'present',
            'leave' => true, // This should be rejected
            'idempotency_key' => 'test-key',
        ];
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/1/mark", $payload);
            
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['leave']);
    }

    public function test_legacy_endpoints_return_410_when_softkill_enabled(): void
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
}
