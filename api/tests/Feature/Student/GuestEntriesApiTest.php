<?php

namespace Tests\Feature\Student;

use App\Domain\GuestEntries\Models\GuestEntry;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestEntriesApiTest extends TestCase
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

    public function test_student_can_create_guest_entry_with_single_guest()
    {
        $data = [
            'guests' => [
                [
                    'name' => 'John Doe',
                    'phone' => '9876543210',
                    'relationship' => 'Father',
                    'id_type' => 'aadhar_card',
                    'id_number' => '123456789012',
                ],
            ],
            'primary_contact_mobile' => '9876543210',
            'visit_date' => '2025-11-15',
            'check_in_time' => '10:00',
            'check_out_time' => '18:00',
            'purpose_to_visit' => 'Family visit',
        ];

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/guest-entries', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('guest_entries', [
            'student_id' => $this->student->id,
            'primary_contact_mobile' => '9876543210',
            'status' => 'pending',
        ]);

        $guestEntry = GuestEntry::where('student_id', $this->student->id)->first();
        $this->assertStringStartsWith('GST-', $guestEntry->unique_id);
        $this->assertCount(1, $guestEntry->guests);
    }

    public function test_student_can_create_guest_entry_with_multiple_guests()
    {
        $data = [
            'guests' => [
                ['name' => 'John Doe', 'relationship' => 'Father', 'id_type' => 'aadhar_card', 'id_number' => '123456789012'],
                ['name' => 'Jane Doe', 'relationship' => 'Mother', 'id_type' => 'aadhar_card', 'id_number' => '987654321098'],
                ['name' => 'Bob Doe', 'relationship' => 'Brother', 'id_type' => 'driving_license', 'id_number' => 'DL123456'],
            ],
            'primary_contact_mobile' => '9876543210',
            'visit_date' => '2025-11-15',
            'check_in_time' => '10:00',
            'check_out_time' => '18:00',
            'purpose_to_visit' => 'Family visit',
        ];

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/guest-entries', $data);

        $response->assertStatus(201);

        $guestEntry = GuestEntry::where('student_id', $this->student->id)->first();
        $this->assertCount(3, $guestEntry->guests);
    }

    public function test_guest_entry_creation_validates_max_4_guests()
    {
        $data = [
            'guests' => [
                ['name' => 'Guest 1', 'relationship' => 'Father', 'id_type' => 'aadhar_card', 'id_number' => '1'],
                ['name' => 'Guest 2', 'relationship' => 'Mother', 'id_type' => 'aadhar_card', 'id_number' => '2'],
                ['name' => 'Guest 3', 'relationship' => 'Brother', 'id_type' => 'aadhar_card', 'id_number' => '3'],
                ['name' => 'Guest 4', 'relationship' => 'Sister', 'id_type' => 'aadhar_card', 'id_number' => '4'],
                ['name' => 'Guest 5', 'relationship' => 'Uncle', 'id_type' => 'aadhar_card', 'id_number' => '5'],
            ],
            'primary_contact_mobile' => '9876543210',
            'visit_date' => '2025-11-15',
            'check_in_time' => '10:00',
            'check_out_time' => '18:00',
            'purpose_to_visit' => 'Family visit',
        ];

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/guest-entries', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['guests']);
    }

    public function test_guest_entry_creation_validates_time_logic()
    {
        $data = [
            'guests' => [
                ['name' => 'John Doe', 'relationship' => 'Father', 'id_type' => 'aadhar_card', 'id_number' => '123456789012'],
            ],
            'primary_contact_mobile' => '9876543210',
            'visit_date' => '2025-11-15',
            'check_in_time' => '18:00',
            'check_out_time' => '10:00', // check_out before check_in
            'purpose_to_visit' => 'Family visit',
        ];

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/guest-entries', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['check_out_time']);
    }

    public function test_guest_entry_creation_validates_id_type()
    {
        $data = [
            'guests' => [
                [
                    'name' => 'John Doe',
                    'relationship' => 'Father',
                    'id_type' => 'invalid_type',
                    'id_number' => '123456789012',
                ],
            ],
            'primary_contact_mobile' => '9876543210',
            'visit_date' => '2025-11-15',
            'check_in_time' => '10:00',
            'check_out_time' => '18:00',
            'purpose_to_visit' => 'Family visit',
        ];

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/student/guest-entries', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['guests.0.id_type']);
    }
}

