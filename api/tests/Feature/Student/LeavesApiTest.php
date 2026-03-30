<?php

namespace Tests\Feature\Student;

use App\Domain\Leaves\Models\Leave;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeavesApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentUser;
    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup tenant context if needed
        // Create test student user
        $this->studentUser = User::factory()->create(['kind' => 'student']);
        $this->student = Student::factory()->create(['user_id' => $this->studentUser->id]);
    }

    public function test_student_can_list_their_leaves()
    {
        Leave::factory()->count(3)->create(['student_id' => $this->student->id]);
        Leave::factory()->count(2)->create(); // Other student's leaves

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/student/leaves');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'unique_id',
                        'title',
                        'description',
                        'reason_for_leave',
                        'from_date',
                        'to_date',
                        'status',
                        'submitted_date',
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_student_can_create_leave_request()
    {
        $data = [
            'title' => 'Family Emergency',
            'reason_for_leave' => 'Need to attend family function',
            'from_date' => '2025-11-10',
            'to_date' => '2025-11-12',
            'emergency_contact' => '9876543210',
        ];

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/leaves', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'unique_id',
                ],
            ]);

        $this->assertDatabaseHas('leaves', [
            'student_id' => $this->student->id,
            'title' => 'Family Emergency',
            'status' => 'pending',
        ]);

        $leave = Leave::where('student_id', $this->student->id)->first();
        $this->assertStringStartsWith('LEV-', $leave->unique_id);
        $this->assertEquals(8, strlen(substr($leave->unique_id, 4))); // 8 chars after LEV-
    }

    public function test_leave_creation_validates_required_fields()
    {
        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/leaves', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'reason_for_leave', 'from_date', 'to_date']);
    }

    public function test_leave_creation_validates_date_logic()
    {
        $data = [
            'title' => 'Test Leave',
            'reason_for_leave' => 'Test',
            'from_date' => '2025-11-12',
            'to_date' => '2025-11-10', // to_date before from_date
        ];

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/leaves', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to_date']);
    }

    public function test_student_can_view_their_leave_details()
    {
        $leave = Leave::factory()->create(['student_id' => $this->student->id]);

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/student/leaves/{$leave->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => (string) $leave->id,
                    'unique_id' => $leave->unique_id,
                ],
            ]);
    }

    public function test_student_cannot_view_other_students_leaves()
    {
        $otherStudent = Student::factory()->create();
        $leave = Leave::factory()->create(['student_id' => $otherStudent->id]);

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/student/leaves/{$leave->id}");

        $response->assertStatus(404);
    }

    public function test_idempotency_key_prevents_duplicates()
    {
        $data = [
            'title' => 'Test Leave',
            'reason_for_leave' => 'Test',
            'from_date' => '2025-11-10',
            'to_date' => '2025-11-12',
            'idempotency_key' => 'test-unique-key-123',
        ];

        // First request
        $response1 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/leaves', $data);

        $response1->assertStatus(201);
        $leaveId = $response1->json('data.id');

        // Duplicate request with same idempotency_key
        $response2 = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/leaves', $data);

        $response2->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $leaveId,
                    'message' => 'Leave request already created',
                ],
            ]);

        // Verify only one leave created
        $this->assertCount(1, Leave::where('idempotency_key', 'test-unique-key-123')->get());
    }
}

