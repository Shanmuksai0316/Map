<?php

namespace Tests\Feature\AttendanceV2;

use App\Domain\Attendance\Models\AttendanceSessionV2;
use App\Models\AttendanceLog;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomBed;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkSubmitTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_endpoint_creates_attendance_log(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $room = Room::factory()->create(['hostel_id' => $hostel->id]);
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);
        
        $roomBed = RoomBed::factory()->create([
            'room_id' => $room->id,
        ]);
        
        \App\Models\RoomAllocation::factory()->create([
            'room_bed_id' => $roomBed->id,
            'student_id' => $student->id,
            'is_active' => true,
        ]);
        
        $session = AttendanceSessionV2::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_date' => now('Asia/Kolkata')->toDateString(),
            'status' => 'active',
            'scheduled_at' => now(),
        ]);
        
        $payload = [
            'student_id' => $student->id,
            'mark' => 'present',
            'idempotency_key' => 'test-key-123',
        ];
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/mark", $payload);
            
        $response->assertStatus(200)
            ->assertJsonStructure([
                'ok',
                'counts' => ['present', 'absent', 'unmarked']
            ]);
            
        $this->assertDatabaseHas('attendance_logs', [
            'tenant_id' => $tenant->id,
            'session_id' => $session->id,
            'status' => 'present',
            'marked_by' => $user->id,
        ]);
    }

    public function test_mark_endpoint_rejects_leave_in_payload(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $room = Room::factory()->create(['hostel_id' => $hostel->id]);
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);
        
        $roomBed = RoomBed::factory()->create([
            'room_id' => $room->id,
        ]);
        
        \App\Models\RoomAllocation::factory()->create([
            'room_bed_id' => $roomBed->id,
            'student_id' => $student->id,
            'is_active' => true,
        ]);
        
        $session = AttendanceSessionV2::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_date' => now('Asia/Kolkata')->toDateString(),
            'status' => 'active',
            'scheduled_at' => now(),
        ]);
        
        $payload = [
            'student_id' => $student->id,
            'mark' => 'present',
            'leave' => true, // This should be rejected
            'idempotency_key' => 'test-key-123',
        ];
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/mark", $payload);
            
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['leave']);
    }

    public function test_mark_endpoint_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $room = Room::factory()->create(['hostel_id' => $hostel->id]);
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);
        
        $roomBed = RoomBed::factory()->create([
            'room_id' => $room->id,
        ]);
        
        \App\Models\RoomAllocation::factory()->create([
            'room_bed_id' => $roomBed->id,
            'student_id' => $student->id,
            'is_active' => true,
        ]);
        
        $session = AttendanceSessionV2::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_date' => now('Asia/Kolkata')->toDateString(),
            'status' => 'active',
            'scheduled_at' => now(),
        ]);
        
        $payload = [
            'student_id' => $student->id,
            'mark' => 'present',
            'idempotency_key' => 'same-key-123',
        ];
        
        // First call
        $response1 = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/mark", $payload);
        $response1->assertStatus(200);
        
        // Second call with same idempotency key
        $response2 = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/mark", $payload);
        $response2->assertStatus(200);
        
        // Should only have one attendance log
        $this->assertDatabaseCount('attendance_logs', 1);
    }

    public function test_submit_endpoint_locks_room_when_all_marked(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $room = Room::factory()->create(['hostel_id' => $hostel->id]);
        $student1 = Student::factory()->create(['tenant_id' => $tenant->id]);
        $student2 = Student::factory()->create(['tenant_id' => $tenant->id]);
        
        $roomBed1 = RoomBed::factory()->create([
            'room_id' => $room->id,
        ]);
        
        $roomBed2 = RoomBed::factory()->create([
            'room_id' => $room->id,
        ]);
        
        \App\Models\RoomAllocation::factory()->create([
            'room_bed_id' => $roomBed1->id,
            'student_id' => $student1->id,
            'is_active' => true,
        ]);
        
        \App\Models\RoomAllocation::factory()->create([
            'room_bed_id' => $roomBed2->id,
            'student_id' => $student2->id,
            'is_active' => true,
        ]);
        
        $session = AttendanceSessionV2::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_date' => now('Asia/Kolkata')->toDateString(),
            'status' => 'active',
            'scheduled_at' => now(),
        ]);
        
        // Mark both students
        AttendanceLog::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_id' => $session->id,
            'attendance_session_id' => $session->id,
            'attendance_date' => now()->toDateString(),
            'student_id' => $student1->id,
            'status' => 'present',
            'marked_at' => now(),
            'marked_by' => $user->id,
        ]);
        
        AttendanceLog::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_id' => $session->id,
            'attendance_session_id' => $session->id,
            'attendance_date' => now()->toDateString(),
            'student_id' => $student2->id,
            'status' => 'absent',
            'marked_at' => now(),
            'marked_by' => $user->id,
        ]);
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/submit");
            
        $response->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'locked' => true,
            ]);
        
        // Check that room was marked as submitted in session metadata
        $session->refresh();
        $this->assertContains($room->id, $session->metadata['submitted_rooms'] ?? []);
    }

    public function test_submit_endpoint_rejects_when_not_all_marked(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $room = Room::factory()->create(['hostel_id' => $hostel->id]);
        $student1 = Student::factory()->create(['tenant_id' => $tenant->id]);
        $student2 = Student::factory()->create(['tenant_id' => $tenant->id]);
        
        $roomBed1 = RoomBed::factory()->create([
            'room_id' => $room->id,
        ]);
        
        $roomBed2 = RoomBed::factory()->create([
            'room_id' => $room->id,
        ]);
        
        \App\Models\RoomAllocation::factory()->create([
            'room_bed_id' => $roomBed1->id,
            'student_id' => $student1->id,
            'is_active' => true,
        ]);
        
        \App\Models\RoomAllocation::factory()->create([
            'room_bed_id' => $roomBed2->id,
            'student_id' => $student2->id,
            'is_active' => true,
        ]);
        
        $session = AttendanceSessionV2::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_date' => now('Asia/Kolkata')->toDateString(),
            'status' => 'active',
            'scheduled_at' => now(),
        ]);
        
        // Mark only one student
        AttendanceLog::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_id' => $session->id,
            'attendance_session_id' => $session->id,
            'attendance_date' => now()->toDateString(),
            'student_id' => $student1->id,
            'status' => 'present',
            'marked_at' => now(),
            'marked_by' => $user->id,
        ]);
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/submit");
            
        $response->assertStatus(400)
            ->assertJson(['error' => 'All students must be marked before submitting']);
    }

    public function test_mark_endpoint_validates_student_belongs_to_room(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $room = Room::factory()->create(['hostel_id' => $hostel->id]);
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);
        // Student not assigned to this room
        
        $session = AttendanceSessionV2::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_date' => now('Asia/Kolkata')->toDateString(),
            'status' => 'active',
            'scheduled_at' => now(),
        ]);
        
        $payload = [
            'student_id' => $student->id,
            'mark' => 'present',
            'idempotency_key' => 'test-key-123',
        ];
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/mark", $payload);
            
        $response->assertStatus(404)
            ->assertJson(['error' => 'Student not found in this room']);
    }
}
