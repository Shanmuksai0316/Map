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
    
    $this->ticket = Ticket::factory()->open()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'reporter_student_id' => $this->studentRecord->id,
        'created_by_user_id' => $this->student->id,
    ]);
});

test('ticket can transition from open to in_progress', function () {
    Sanctum::actingAs($this->hkSupervisor, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/status", [
        'status' => 'in_progress',
    ]);
    
    $response->assertStatus(200);
    
    $this->assertDatabaseHas('tickets', [
        'id' => $this->ticket->id,
        'status' => 'in_progress',
    ]);
});

test('ticket can transition from in_progress to resolved', function () {
    $this->ticket->update(['status' => 'in_progress']);
    
    Sanctum::actingAs($this->hkSupervisor, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/status", [
        'status' => 'resolved',
    ]);
    
    $response->assertStatus(200);
    
    $this->assertDatabaseHas('tickets', [
        'id' => $this->ticket->id,
        'status' => 'resolved',
        'closed_at' => now()->toDateTimeString(),
    ]);
});

test('ticket can transition from resolved to closed', function () {
    $this->ticket->update(['status' => 'resolved']);
    
    Sanctum::actingAs($this->campusManager, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/status", [
        'status' => 'closed',
    ]);
    
    $response->assertStatus(200);
    
    $this->assertDatabaseHas('tickets', [
        'id' => $this->ticket->id,
        'status' => 'closed',
    ]);
});

test('ticket can be reopened from closed', function () {
    $this->ticket->update(['status' => 'closed']);
    
    Sanctum::actingAs($this->campusManager, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/status", [
        'status' => 'open',
    ]);
    
    $response->assertStatus(200);
    
    $this->assertDatabaseHas('tickets', [
        'id' => $this->ticket->id,
        'status' => 'open',
        'closed_at' => null,
    ]);
});

test('ticket cannot make invalid transitions', function () {
    Sanctum::actingAs($this->hkSupervisor, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/status", [
        'status' => 'resolved', // Cannot go directly from open to resolved
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('student cannot change ticket status', function () {
    Sanctum::actingAs($this->student, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/status", [
        'status' => 'in_progress',
    ]);
    
    $response->assertStatus(403);
});

test('status change validates required fields', function () {
    Sanctum::actingAs($this->hkSupervisor, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/status", []);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('status change validates enum values', function () {
    Sanctum::actingAs($this->hkSupervisor, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/status", [
        'status' => 'invalid_status',
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('ticket detail shows all information', function () {
    Sanctum::actingAs($this->student, ['*']);
    
    $response = $this->getJson("/api/v1/tickets/{$this->ticket->id}");
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'category',
                'priority',
                'status',
                'title',
                'description',
                'sla_due_at',
                'created_at',
                'is_within_sla',
                'is_breached',
                'reporter_name',
                'reporter_type',
                'hostel',
                'reporter_student',
            ],
        ]);
});
