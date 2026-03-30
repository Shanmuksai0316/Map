<?php

namespace Tests\Feature\Sports;

use App\Enums\SportsEventStatus;
use App\Enums\SportsEnrollmentStatus;
use App\Models\SportsEvent;
use App\Models\SportsEnrollment;
use App\Models\Student;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportsLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private array $context = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->setupSportsContext();
    }

    private function setupSportsContext(): array
    {
        // Create roles
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Campus Manager', 'guard_name' => 'sanctum']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Sports Manager', 'guard_name' => 'sanctum']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'sanctum']);

        // Create tenant
        $tenant = \App\Models\Tenant::factory()->create();

        // Create campus manager
        $campusManager = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Campus Manager',
            'email' => 'campus@example.com',
        ]);
        $campusManager->assignRole('Campus Manager');

        // Create sports coordinator
        $sportsCoordinator = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Sports Coordinator',
            'email' => 'sports@example.com',
        ]);
        $sportsCoordinator->assignRole('Sports Manager');

        // Create student user and student model
        $studentUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Student User',
            'email' => 'student@example.com',
        ]);
        $studentUser->assignRole('Student');

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $studentUser->id,
            'map_student_id' => 'STU001',
        ]);

        return [
            'tenant' => $tenant,
            'campusManager' => $campusManager,
            'sportsCoordinator' => $sportsCoordinator,
            'studentUser' => $studentUser,
            'student' => $student,
        ];
    }

    /** @test */
    public function sports_module_feature_flag_controls_access()
    {
        // Test with feature disabled
        config(['features.sports_module' => false]);
        
        $this->actingAs($this->context['campusManager'])
            ->getJson('/api/v1/sports/events')
            ->assertStatus(404);

        // Test with feature enabled
        config(['features.sports_module' => true]);
        
        $this->actingAs($this->context['campusManager'])
            ->getJson('/api/v1/sports/events')
            ->assertStatus(200);
    }

    /** @test */
    public function sports_events_lifecycle_works_correctly()
    {
        config(['features.sports_module' => true]);

        $eventData = [
            'sport' => 'Basketball',
            'name' => 'Basketball Tournament',
            'description' => 'Annual basketball tournament',
            'scheduled_at' => now()->addDays(7)->toISOString(),
            'end_time' => now()->addDays(7)->addHours(3)->toISOString(),
            'venue' => 'Sports Complex',
            'capacity' => 20,
            'registration_deadline' => now()->addDays(5)->toISOString(),
            'requirements' => 'Sports shoes required',
        ];

        // Campus manager can create events
        $response = $this->actingAs($this->context['campusManager'])
            ->postJson('/api/v1/sports/events', $eventData)
            ->assertStatus(201);

        $eventId = $response->json('data.id');
        $event = SportsEvent::find($eventId);

        $this->assertEquals(SportsEventStatus::SCHEDULED, $event->status);
        $this->assertEquals($eventData['capacity'], $event->capacity);

        // Campus manager can update events
        $updateData = [
            'capacity' => 25,
            'description' => 'Updated tournament description',
        ];

        $this->actingAs($this->context['campusManager'])
            ->putJson("/api/v1/sports/events/{$eventId}", $updateData)
            ->assertStatus(202);

        $event->refresh();
        $this->assertEquals(25, $event->capacity);
        $this->assertEquals('Updated tournament description', $event->description);

        // Event status transitions work
        $this->assertTrue($event->canTransitionTo(SportsEventStatus::ONGOING));
        $event->transitionTo(SportsEventStatus::ONGOING);
        $this->assertEquals(SportsEventStatus::ONGOING, $event->status);

        $this->assertTrue($event->canTransitionTo(SportsEventStatus::COMPLETED));
        $event->transitionTo(SportsEventStatus::COMPLETED);
        $this->assertEquals(SportsEventStatus::COMPLETED, $event->status);

        // Cannot transition from completed to other states
        $this->assertFalse($event->canTransitionTo(SportsEventStatus::ONGOING));
    }

    /** @test */
    public function sports_enrollment_capacity_management_works_correctly()
    {
        config(['features.sports_module' => true]);

        // Create event with capacity of 2
        $event = SportsEvent::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
            'capacity' => 2,
            'status' => SportsEventStatus::SCHEDULED,
        ]);

        // Create additional students
        $student2 = Student::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
            'user_id' => User::factory()->create(['tenant_id' => $this->context['tenant']->id])->id,
            'map_student_id' => 'STU002',
        ]);

        $student3 = Student::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
            'user_id' => User::factory()->create(['tenant_id' => $this->context['tenant']->id])->id,
            'map_student_id' => 'STU003',
        ]);

        // First enrollment should be registered
        $enrollment1 = $this->actingAs($this->context['campusManager'])
            ->postJson("/api/v1/sports/events/{$event->id}/enrollments", [
                'student_id' => $this->context['student']->id,
            ])
            ->assertStatus(201);

        $this->assertEquals('registered', $enrollment1->json('data.status'));

        // Second enrollment should be registered
        $enrollment2 = $this->actingAs($this->context['campusManager'])
            ->postJson("/api/v1/sports/events/{$event->id}/enrollments", [
                'student_id' => $student2->id,
            ])
            ->assertStatus(201);

        $this->assertEquals('registered', $enrollment2->json('data.status'));

        // Third enrollment should be waitlisted
        $enrollment3 = $this->actingAs($this->context['campusManager'])
            ->postJson("/api/v1/sports/events/{$event->id}/enrollments", [
                'student_id' => $student3->id,
            ])
            ->assertStatus(201);

        $this->assertEquals('waitlisted', $enrollment3->json('data.status'));
        $this->assertEquals(1, $enrollment3->json('data.waitlist_position'));

        // Cannot enroll same student twice
        $this->actingAs($this->context['campusManager'])
            ->postJson("/api/v1/sports/events/{$event->id}/enrollments", [
                'student_id' => $this->context['student']->id,
            ])
            ->assertStatus(409);
    }

    /** @test */
    public function sports_enrollment_status_transitions_work_correctly()
    {
        config(['features.sports_module' => true]);

        $event = SportsEvent::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
            'capacity' => 10,
            'status' => SportsEventStatus::SCHEDULED,
        ]);

        $enrollment = SportsEnrollment::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
            'sports_event_id' => $event->id,
            'student_id' => $this->context['student']->id,
            'status' => SportsEnrollmentStatus::REGISTERED,
        ]);

        // Campus manager can mark as attended
        $this->actingAs($this->context['campusManager'])
            ->putJson("/api/v1/sports/events/{$event->id}/enrollments/{$enrollment->id}", [
                'status' => 'attended',
                'notes' => 'Student participated well',
            ])
            ->assertStatus(202);

        $enrollment->refresh();
        $this->assertEquals(SportsEnrollmentStatus::ATTENDED, $enrollment->status);
        $this->assertNotNull($enrollment->attended_at);

        // Create another enrollment to test no-show
        $enrollment2 = SportsEnrollment::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
            'sports_event_id' => $event->id,
            'student_id' => Student::factory()->create([
                'tenant_id' => $this->context['tenant']->id,
                'user_id' => User::factory()->create(['tenant_id' => $this->context['tenant']->id])->id,
            ])->id,
            'status' => SportsEnrollmentStatus::REGISTERED,
        ]);

        $this->actingAs($this->context['sportsCoordinator'])
            ->putJson("/api/v1/sports/events/{$event->id}/enrollments/{$enrollment2->id}", [
                'status' => 'no_show',
            ])
            ->assertStatus(202);

        $enrollment2->refresh();
        $this->assertEquals(SportsEnrollmentStatus::NO_SHOW, $enrollment2->status);
        $this->assertNotNull($enrollment2->attended_at);
    }


    /** @test */
    public function students_can_only_access_their_own_enrollments()
    {
        config(['features.sports_module' => true]);

        // Create another student
        $otherStudent = Student::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
            'user_id' => User::factory()->create(['tenant_id' => $this->context['tenant']->id])->id,
        ]);

        $event = SportsEvent::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
        ]);

        $enrollment = SportsEnrollment::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
            'sports_event_id' => $event->id,
            'student_id' => $otherStudent->id,
        ]);

        // Equipment loans removed in project

        // Student cannot view other student's enrollment
        $this->actingAs($this->context['studentUser'])
            ->getJson("/api/v1/sports/events/{$event->id}/enrollments/{$enrollment->id}")
            ->assertStatus(403);

        // Equipment loans removed in project

        // But student can view their own enrollments and loans
        $ownEnrollment = SportsEnrollment::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
            'sports_event_id' => $event->id,
            'student_id' => $this->context['student']->id,
        ]);

        // Equipment loans removed in project

        $this->actingAs($this->context['studentUser'])
            ->getJson("/api/v1/sports/events/{$event->id}/enrollments/{$ownEnrollment->id}")
            ->assertStatus(200);

        // Equipment loans removed in project
    }

    /** @test */
    public function role_based_access_control_works_correctly()
    {
        config(['features.sports_module' => true]);

        // Create user without sports roles
        $regularUser = User::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
        ]);

        $event = SportsEvent::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
        ]);

        // Regular user cannot create events
        $this->actingAs($regularUser)
            ->postJson('/api/v1/sports/events', [
                'sport' => 'Football',
                'name' => 'Test Event',
                'scheduled_at' => now()->addDays(1)->toISOString(),
            ])
            ->assertStatus(403);

        // Regular user cannot view events
        $this->actingAs($regularUser)
            ->getJson('/api/v1/sports/events')
            ->assertStatus(403);

        // Campus manager can create events
        $this->actingAs($this->context['campusManager'])
            ->postJson('/api/v1/sports/events', [
                'sport' => 'Football',
                'name' => 'Test Event',
                'scheduled_at' => now()->addDays(1)->toISOString(),
            ])
            ->assertStatus(201);

        // Sports coordinator can create events
        $this->actingAs($this->context['sportsCoordinator'])
            ->postJson('/api/v1/sports/events', [
                'sport' => 'Tennis',
                'name' => 'Tennis Tournament',
                'scheduled_at' => now()->addDays(2)->toISOString(),
            ])
            ->assertStatus(201);
    }
}
