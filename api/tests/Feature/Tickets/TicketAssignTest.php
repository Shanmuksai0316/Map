<?php

use App\Domain\Tickets\Models\Ticket;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'HKSupervisor', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'CampusManager', 'guard_name' => 'sanctum']);
    
    $this->tenant = Tenant::factory()->create();
    $this->hostel = Hostel::factory()->create(['tenant_id' => $this->tenant->id]);
    
    $this->student = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->student->assignRole('Student');
    
    $this->studentRecord = Student::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'user_id' => $this->student->id,
    ]);
    
    $this->hkSupervisor = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->hkSupervisor->assignRole('HKSupervisor');
    
    $this->campusManager = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->campusManager->assignRole('CampusManager');
    
    $this->assignee = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->assignee->assignRole('HKSupervisor');
    
    $this->ticket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'reporter_student_id' => $this->studentRecord->id,
        'created_by_user_id' => $this->student->id,
    ]);
});

test('hk supervisor can assign ticket in their hostel', function () {
    Sanctum::actingAs($this->hkSupervisor, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/assign", [
        'assignee_user_id' => $this->assignee->id,
    ]);
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'assignee_user',
            ],
            'message',
        ]);
    
    $this->assertDatabaseHas('tickets', [
        'id' => $this->ticket->id,
        'assignee_user_id' => $this->assignee->id,
    ]);
});

test('campus manager can assign any ticket', function () {
    Sanctum::actingAs($this->campusManager, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/assign", [
        'assignee_user_id' => $this->assignee->id,
    ]);
    
    $response->assertStatus(200);
    
    $this->assertDatabaseHas('tickets', [
        'id' => $this->ticket->id,
        'assignee_user_id' => $this->assignee->id,
    ]);
});

test('student cannot assign ticket', function () {
    Sanctum::actingAs($this->student, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/assign", [
        'assignee_user_id' => $this->assignee->id,
    ]);
    
    $response->assertStatus(403);
});

test('assignment validates assignee exists', function () {
    Sanctum::actingAs($this->campusManager, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/assign", [
        'assignee_user_id' => 99999, // Non-existent user
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['assignee_user_id']);
});

test('assignment validates required fields', function () {
    Sanctum::actingAs($this->campusManager, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/assign", []);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['assignee_user_id']);
});
