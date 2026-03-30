<?php

namespace Tests\Feature\Student;

use App\Domain\SickLeaves\Models\SickLeave;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SickLeavesApiTest extends TestCase
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

    public function test_student_can_list_their_sick_leaves()
    {
        SickLeave::factory()->count(3)->create(['student_id' => $this->student->id]);

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/student/sick-leaves');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'unique_id',
                        'title',
                        'illness',
                        'need_medical_attention',
                        'contact_parents',
                        'status',
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_student_can_create_sick_leave_request()
    {
        $data = [
            'title' => 'Fever and Cold',
            'illness' => 'Fever and Cold',
            'illness_details' => 'High fever and severe cold symptoms',
            'need_medical_attention' => true,
            'contact_parents' => false,
        ];

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/sick-leaves', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('sick_leaves', [
            'student_id' => $this->student->id,
            'illness' => 'Fever and Cold',
            'need_medical_attention' => true,
            'contact_parents' => false,
            'status' => 'pending',
        ]);

        $sickLeave = SickLeave::where('student_id', $this->student->id)->first();
        $this->assertStringStartsWith('SLK-', $sickLeave->unique_id);
    }

    public function test_sick_leave_creation_validates_required_fields()
    {
        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/sick-leaves', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'illness', 'illness_details', 'need_medical_attention', 'contact_parents']);
    }

    public function test_sick_leave_creation_validates_boolean_fields()
    {
        $data = [
            'title' => 'Test',
            'illness' => 'Test',
            'illness_details' => 'Test',
            'need_medical_attention' => 'not-boolean',
            'contact_parents' => 'not-boolean',
        ];

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/sick-leaves', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['need_medical_attention', 'contact_parents']);
    }
}

