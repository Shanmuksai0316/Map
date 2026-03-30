<?php

namespace Tests\Feature\Attendance;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    // RefreshDatabase is already included in TestCase

    public function test_warden_can_get_today_session(): void
    {
        // Disable legacy soft-kill for this test
        config(['features.attendance_legacy_softkill' => false]);
        
        // Create roles
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'sanctum']);

        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'curfew_time' => '22:30:00',
        ]);

        $warden = User::factory()->create(['tenant_id' => $tenant->id]);
        $warden->assignRole('Warden');
        
        // Assign warden to hostel (required for HostelScope)
        // Use the relationship method to ensure proper pivot setup
        $warden->staffHostels()->attach($hostel->id, [
            'tenant_id' => $tenant->id,
            'assigned_at' => now(),
            'revoked_at' => null, // Ensure revoked_at is null
        ]);

        // Create session - must match today's date in Asia/Kolkata timezone
        $today = Carbon::now('Asia/Kolkata');
        $session = AttendanceSession::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'kind' => 'night_check',
            'status' => 'in_progress', // Valid status: pending, in_progress, completed
            'scheduled_at' => $today, // Must match today's date
            'session_date' => $today->toDateString(),
            'session_time' => $today->toTimeString(),
            'metadata' => [
                'open_at' => $today->subHour()->toISOString(),
                'close_at' => $today->addHour()->toISOString(),
                'session_date' => $today->toDateString(),
            ],
        ]);

        Sanctum::actingAs($warden, ['*']);

        $response = $this->getJson('/api/v1/attendance/today');

        $response->assertOk()
            ->assertJson([
                'id' => $session->id,
                'hostel_id' => $session->hostel_id,
                'status' => 'in_progress', // Updated to match database constraint
            ]);
    }

    public function test_warden_can_get_rooms_for_session(): void
    {
        // Disable legacy soft-kill for this test
        config(['features.attendance_legacy_softkill' => false]);
        
        // Create roles
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'sanctum']);

        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'curfew_time' => '22:30:00',
        ]);

        $warden = User::factory()->create(['tenant_id' => $tenant->id]);
        $warden->assignRole('Warden');
        
        // Assign warden to hostel (required for HostelScope)
        // Use the relationship method to ensure proper pivot setup
        $warden->staffHostels()->attach($hostel->id, [
            'tenant_id' => $tenant->id,
            'assigned_at' => now(),
            'revoked_at' => null, // Ensure revoked_at is null
        ]);

        // Create session - must match today's date in Asia/Kolkata timezone
        $today = Carbon::now('Asia/Kolkata');
        $session = AttendanceSession::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'kind' => 'night_check',
            'status' => 'in_progress', // Valid status: pending, in_progress, completed
            'scheduled_at' => $today, // Must match today's date
            'session_date' => $today->toDateString(),
            'session_time' => $today->toTimeString(),
            'metadata' => [
                'open_at' => $today->copy()->subHour()->toISOString(),
                'close_at' => $today->copy()->addHour()->toISOString(),
                'session_date' => $today->toDateString(),
            ],
        ]);

        Sanctum::actingAs($warden, ['*']);

        $response = $this->getJson("/api/v1/attendance/room/{$session->id}/1");

        $response->assertOk();
        $this->assertIsArray($response->json());
    }

    public function test_student_cannot_access_attendance_apis(): void
    {
        // Disable legacy soft-kill for this test
        config(['features.attendance_legacy_softkill' => false]);
        
        // Create roles
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'sanctum']);

        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'curfew_time' => '22:30:00',
        ]);

        $student = User::factory()->create(['tenant_id' => $tenant->id]);
        $student->assignRole('Student');

        // Create session
        $session = AttendanceSession::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'kind' => 'night_check',
            'status' => 'in_progress', // Valid status: pending, in_progress, completed
        ]);

        Sanctum::actingAs($student, ['*']);

        $response = $this->getJson('/api/v1/attendance/today');
        $response->assertStatus(403);

        $response = $this->getJson("/api/v1/attendance/room/{$session->id}/1");
        $response->assertStatus(403);
    }
}
