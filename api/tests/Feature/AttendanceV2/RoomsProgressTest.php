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

class RoomsProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_rooms_endpoint_returns_room_progress(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $room = Room::factory()->create([
            'hostel_id' => $hostel->id,
            'block_code' => 'A',
            'floor_code' => '1',
            'number' => 101,
        ]);
        
        $session = AttendanceSessionV2::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_date' => now('Asia/Kolkata')->toDateString(),
            'status' => 'active',
            'scheduled_at' => now(),
        ]);
        
        // Create students in the room
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
        
        // Mark one student as present
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
            ->getJson("/api/v1/attendance/sessions/{$session->id}/rooms");
            
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'room_id',
                        'room',
                        'counts' => [
                            'total',
                            'present',
                            'absent',
                            'unmarked',
                        ],
                        'percent_complete',
                    ]
                ]
            ]);
            
        $roomData = $response->json('data')[0];
        $this->assertEquals($room->id, $roomData['room_id']);
        $this->assertEquals('A-1101', $roomData['room']);
        $this->assertEquals(2, $roomData['counts']['total']);
        $this->assertEquals(1, $roomData['counts']['present']);
        $this->assertEquals(0, $roomData['counts']['absent']);
        $this->assertEquals(1, $roomData['counts']['unmarked']);
        $this->assertEquals(50.0, $roomData['percent_complete']);
    }

    public function test_rooms_endpoint_excludes_rooms_with_no_students(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $roomWithStudents = Room::factory()->create(['hostel_id' => $hostel->id]);
        $emptyRoom = Room::factory()->create(['hostel_id' => $hostel->id]);
        
        $session = AttendanceSessionV2::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_date' => now('Asia/Kolkata')->toDateString(),
            'status' => 'active',
            'scheduled_at' => now(),
        ]);
        
        // Add student only to one room
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);
        $roomBed = RoomBed::factory()->create([
            'room_id' => $roomWithStudents->id,
        ]);
        
        \App\Models\RoomAllocation::factory()->create([
            'room_bed_id' => $roomBed->id,
            'student_id' => $student->id,
            'is_active' => true,
        ]);
        
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/attendance/sessions/{$session->id}/rooms");
            
        $response->assertStatus(200);
        
        $rooms = $response->json('data');
        $this->assertCount(1, $rooms);
        $this->assertEquals($roomWithStudents->id, $rooms[0]['room_id']);
        $this->assertEquals(1, $rooms[0]['counts']['total']);
    }

    public function test_rooms_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/attendance/sessions/1/rooms');
        $response->assertStatus(401);
    }

    public function test_rooms_endpoint_respects_authorization(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        $hostel1 = Hostel::factory()->create(['tenant_id' => $tenant1->id]);
        $hostel2 = Hostel::factory()->create(['tenant_id' => $tenant2->id]);
        
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        
        $session = AttendanceSessionV2::create([
            'tenant_id' => $tenant1->id,
            'hostel_id' => $hostel1->id,
            'session_date' => now('Asia/Kolkata')->toDateString(),
            'status' => 'active',
            'scheduled_at' => now(),
        ]);
        
        // User2 should not access tenant1's session
        $response = $this->actingAs($user2, 'sanctum')
            ->getJson("/api/v1/attendance/sessions/{$session->id}/rooms");
            
        $response->assertStatus(403)
            ->assertJson(['error' => 'Unauthorized']);
    }
}
