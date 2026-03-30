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
    Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'sanctum']);
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
    
    $this->warden = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->warden->assignRole('Warden');
    
    $this->campusManager = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->campusManager->assignRole('CampusManager');
});

test('student can create ticket', function () {
    Sanctum::actingAs($this->student, ['*']);
    
    $response = $this->postJson('/api/v1/tickets', [
        'category' => 'housekeeping',
        'priority' => 'medium',
        'title' => 'Broken faucet in bathroom',
        'description' => 'The faucet in the common bathroom is leaking and needs repair.',
        'hostel_id' => $this->hostel->id,
    ]);
    
    $response->assertStatus(201)
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
                'hostel',
                'reporter_student',
            ],
            'message',
        ]);
    
    $this->assertDatabaseHas('tickets', [
        'title' => 'Broken faucet in bathroom',
        'category' => 'housekeeping',
        'priority' => 'medium',
        'status' => 'open',
        'reporter_student_id' => $this->studentRecord->id,
        'hostel_id' => $this->hostel->id,
    ]);
});

test('student can list their own tickets', function () {
    // Create tickets
    $myTicket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'reporter_student_id' => $this->studentRecord->id,
        'created_by_user_id' => $this->student->id,
    ]);
    
    $otherTicket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'created_by_user_id' => $this->warden->id,
    ]);
    
    Sanctum::actingAs($this->student, ['*']);
    
    $response = $this->getJson('/api/v1/tickets?mine=true');
    
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $myTicket->id);
});

test('warden can list tickets in their hostel', function () {
    // Create tickets
    $ticket1 = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
    ]);
    
    $ticket2 = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
    ]);
    
    Sanctum::actingAs($this->warden, ['*']);
    
    $response = $this->getJson('/api/v1/tickets');
    
    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('campus manager can list all tickets', function () {
    // Create tickets
    $ticket1 = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
    ]);
    
    $ticket2 = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
    ]);
    
    Sanctum::actingAs($this->campusManager, ['*']);
    
    $response = $this->getJson('/api/v1/tickets');
    
    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('ticket creation validates required fields', function () {
    Sanctum::actingAs($this->student, ['*']);
    
    $response = $this->postJson('/api/v1/tickets', [
        'title' => 'Test ticket',
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['category', 'description', 'hostel_id']);
});

test('ticket creation validates category enum', function () {
    Sanctum::actingAs($this->student, ['*']);
    
    $response = $this->postJson('/api/v1/tickets', [
        'category' => 'invalid_category',
        'title' => 'Test ticket',
        'description' => 'Test description',
        'hostel_id' => $this->hostel->id,
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['category']);
});

test('ticket list supports filtering by status', function () {
    $openTicket = Ticket::factory()->open()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
    ]);
    
    $closedTicket = Ticket::factory()->closed()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
    ]);
    
    Sanctum::actingAs($this->campusManager, ['*']);
    
    $response = $this->getJson('/api/v1/tickets?status[]=open');
    
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $openTicket->id);
});

test('ticket list supports filtering by category', function () {
    $housekeepingTicket = Ticket::factory()->housekeeping()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
    ]);
    
    $maintenanceTicket = Ticket::factory()->maintenance()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
    ]);
    
    Sanctum::actingAs($this->campusManager, ['*']);
    
    $response = $this->getJson('/api/v1/tickets?category[]=housekeeping');
    
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $housekeepingTicket->id);
});

test('ticket list supports search', function () {
    $ticket1 = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'title' => 'Broken faucet',
        'description' => 'The faucet is broken',
    ]);
    
    $ticket2 = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'title' => 'Light bulb issue',
        'description' => 'The light bulb needs replacement',
    ]);
    
    Sanctum::actingAs($this->campusManager, ['*']);
    
    $response = $this->getJson('/api/v1/tickets?q=faucet');
    
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ticket1->id);
});
