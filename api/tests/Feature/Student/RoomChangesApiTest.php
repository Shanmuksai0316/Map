<?php

namespace Tests\Feature\Student;

use App\Domain\RoomChanges\Models\RoomChange;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomChangesApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentUser;
    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->studentUser = User::factory()->create(['kind' => 'student']);
        $this->student = Student::factory()->create(['user_id' => $this->studentUser->id]);
    }

    public function test_student_can_list_their_room_changes()
    {
        RoomChange::factory()->count(3)->create(['student_id' => $this->student->id]);

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/student/room-changes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'unique_id',
                        'title',
                        'description',
                        'status',
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_student_can_create_room_change_request()
    {
        $data = [
            'description' => 'Need to move to a different room',
            'preferred_room_number' => '205',
            'preferred_floor' => '2nd Floor',
            'sharing_preference' => 'double',
            'date_required' => '2025-11-20',
        ];

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/room-changes', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('room_changes', [
            'student_id' => $this->student->id,
            'description' => 'Need to move to a different room',
            'sharing_preference' => 'double',
            'status' => 'pending',
        ]);

        $roomChange = RoomChange::where('student_id', $this->student->id)->first();
        $this->assertStringStartsWith('RMC-', $roomChange->unique_id);
    }

    public function test_room_change_creation_validates_sharing_preference()
    {
        $data = [
            'description' => 'Test',
            'sharing_preference' => 'invalid',
        ];

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/room-changes', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sharing_preference']);
    }

    public function test_room_change_creation_allows_optional_fields()
    {
        $data = [
            'description' => 'Test description',
            // All other fields optional
        ];

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/room-changes', $data);

        $response->assertStatus(201);

        $roomChange = RoomChange::where('student_id', $this->student->id)->first();
        $this->assertNull($roomChange->preferred_room_number);
        $this->assertNull($roomChange->sharing_preference);
    }
}

