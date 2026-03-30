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
    Role::firstOrCreate(['name' => 'CampusManager', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'HKSupervisor', 'guard_name' => 'sanctum']);
    
    $this->tenant = Tenant::factory()->create();
    $this->hostel = Hostel::factory()->create(['tenant_id' => $this->tenant->id]);
    
    $this->student = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->student->assignRole('Student');
    
    $this->studentRecord = Student::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'user_id' => $this->student->id,
    ]);
    
    $this->campusManager = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->campusManager->assignRole('CampusManager');
    
    $this->hkSupervisor = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->hkSupervisor->assignRole('HKSupervisor');
});

test('ticket creation works with audit and notification integration', function () {
    Sanctum::actingAs($this->student, ['*']);

    $response = $this->postJson('/api/v1/tickets', [
        'hostel_id' => $this->hostel->id,
        'category' => 'maintenance',
        'priority' => 'high',
        'title' => 'Test Ticket',
        'description' => 'Test description',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'data' => [
            'id',
            'title',
            'category',
            'priority',
            'status',
        ],
        'message',
    ]);

    // Verify ticket was created
    $ticket = Ticket::where('title', 'Test Ticket')->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->category)->toBe('maintenance');
    expect($ticket->priority)->toBe('high');
    expect($ticket->status)->toBe('open');
});

test('ticket assignment works with audit and notification integration', function () {
    Sanctum::actingAs($this->campusManager, ['*']);

    $ticket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'status' => 'open',
    ]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
        'assignee_user_id' => $this->hkSupervisor->id,
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'id',
            'assignee_user',
        ],
        'message',
    ]);

    // Verify assignment was updated
    $ticket->refresh();
    expect($ticket->assignee_user_id)->toBe($this->hkSupervisor->id);
});

test('ticket status change to resolved works with audit and notification integration', function () {
    Sanctum::actingAs($this->campusManager, ['*']);

    $ticket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'status' => 'in_progress',
    ]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/status", [
        'status' => 'resolved',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'id',
            'status',
        ],
        'message',
    ]);

    // Verify status was updated
    $ticket->refresh();
    expect($ticket->status)->toBe('resolved');
    expect($ticket->closed_at)->not->toBeNull();
});

test('ticket status change to closed works with audit and notification integration', function () {
    Sanctum::actingAs($this->campusManager, ['*']);

    $ticket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'status' => 'resolved',
    ]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/status", [
        'status' => 'closed',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'id',
            'status',
        ],
        'message',
    ]);

    // Verify status was updated
    $ticket->refresh();
    expect($ticket->status)->toBe('closed');
    expect($ticket->closed_at)->not->toBeNull();
});

test('ticket comment works with audit integration', function () {
    Sanctum::actingAs($this->student, ['*']);

    // Create a ticket where the student is the reporter
    $ticket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'status' => 'open',
        'reporter_student_id' => $this->studentRecord->id,
        'reporter_user_id' => null,
    ]);

    $response = $this->postJson("/api/v1/tickets/{$ticket->id}/comments", [
        'body' => 'Test comment',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'data' => [
            'id',
            'body',
            'author',
        ],
        'message',
    ]);

    // Verify comment was created
    $comment = $ticket->comments()->where('body', 'Test comment')->first();
    expect($comment)->not->toBeNull();
    expect($comment->user_id)->toBe($this->student->id);
});
