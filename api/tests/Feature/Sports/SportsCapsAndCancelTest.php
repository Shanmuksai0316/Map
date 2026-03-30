<?php

namespace Tests\Feature\Sports;

use App\Models\SportsEvent;
use App\Models\SportsEnrollment;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SportsCapsAndCancelTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $sportsManager;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        
        // Create student
        $this->student = User::factory()->create(['tenant_id' => $this->tenant->id]);
        Student::factory()->create(['user_id' => $this->student->id, 'tenant_id' => $this->tenant->id]);
        
        // Create Sports Manager with proper role
        $this->sportsManager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'kind' => 'SportsManager'
        ]);
        
        // Assign Sports Manager role
        $sportsManagerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Sports Manager']);
        $this->sportsManager->assignRole($sportsManagerRole);
        
        Config::set('features.sports_module', true);
    }

    public function test_enrollment_respects_capacity_limits()
    {
        $event = SportsEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'capacity' => 2,
            'scheduled_at' => now()->addDays(1),
        ]);

        // Enroll first student
        $response1 = $this->actingAs($this->student)
            ->postJson("/api/v1/sports/events/{$event->id}/enrollments");

        $response1->assertStatus(201);

        // Create second student
        $student2 = User::factory()->create(['tenant_id' => $this->tenant->id]);
        Student::factory()->create(['user_id' => $student2->id, 'tenant_id' => $this->tenant->id]);

        // Enroll second student
        $response2 = $this->actingAs($student2)
            ->postJson("/api/v1/sports/events/{$event->id}/enrollments");

        $response2->assertStatus(201);

        // Create third student
        $student3 = User::factory()->create(['tenant_id' => $this->tenant->id]);
        Student::factory()->create(['user_id' => $student3->id, 'tenant_id' => $this->tenant->id]);

        // Third enrollment should fail due to capacity
        $response3 = $this->actingAs($student3)
            ->postJson("/api/v1/sports/events/{$event->id}/enrollments");

        $response3->assertStatus(409)
            ->assertJson(['error' => 'Event is at full capacity']);
    }

    public function test_cannot_enroll_twice_in_same_event()
    {
        $event = SportsEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'capacity' => 10,
            'scheduled_at' => now()->addDays(1),
        ]);

        // First enrollment
        $response1 = $this->actingAs($this->student)
            ->postJson("/api/v1/sports/events/{$event->id}/enrollments");

        $response1->assertStatus(201);

        // Second enrollment should fail
        $response2 = $this->actingAs($this->student)
            ->postJson("/api/v1/sports/events/{$event->id}/enrollments");

        $response2->assertStatus(409)
            ->assertJson(['error' => 'Already enrolled in this event']);
    }

    public function test_can_cancel_event_and_all_enrollments()
    {
        $event = SportsEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(1),
        ]);

        // Create enrollments
        $enrollment1 = SportsEnrollment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sports_event_id' => $event->id,
            'status' => SportsEnrollmentStatus::REGISTERED,
        ]);

        $enrollment2 = SportsEnrollment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sports_event_id' => $event->id,
            'status' => SportsEnrollmentStatus::REGISTERED,
        ]);

        // Cancel event
        $response = $this->actingAs($this->sportsManager)
            ->postJson("/api/v1/sports/events/{$event->id}/cancel");

        $response->assertStatus(200);

        // Check event status
        $event->refresh();
        $this->assertEquals('cancelled', $event->status);
        $this->assertNotNull($event->cancelled_at);

        // Check enrollments are cancelled
        $enrollment1->refresh();
        $enrollment2->refresh();
        $this->assertEquals('cancelled', $enrollment1->status);
        $this->assertEquals('cancelled', $enrollment2->status);
    }

    public function test_can_unenroll_from_event()
    {
        $event = SportsEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'scheduled_at' => now()->addDays(1),
        ]);

        $enrollment = SportsEnrollment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sports_event_id' => $event->id,
            'student_id' => $this->student->student->id,
            'status' => SportsEnrollmentStatus::REGISTERED,
        ]);

        $response = $this->actingAs($this->student)
            ->deleteJson("/api/v1/sports/events/{$event->id}/enrollments/{$enrollment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'enrollment_id' => $enrollment->id,
                    'status' => 'cancelled',
                ]
            ]);

        $enrollment->refresh();
        $this->assertEquals('cancelled', $enrollment->status);
        $this->assertNotNull($enrollment->cancelled_at);
    }

    public function test_returns_404_when_sports_module_disabled()
    {
        Config::set('features.sports_module', false);

        $response = $this->actingAs($this->student)
            ->getJson('/api/v1/sports/events');

        $response->assertStatus(404);
    }

    public function test_validates_event_creation_requirements()
    {
        $response = $this->actingAs($this->sportsManager)
            ->postJson('/api/v1/sports/events', [
                'name' => 'Test Event',
                'scheduled_at' => now()->subDay(), // Past date
                'capacity' => 0, // Invalid capacity
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_at', 'capacity']);
    }
}



