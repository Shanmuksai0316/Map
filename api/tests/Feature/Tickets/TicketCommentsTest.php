<?php

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketComment;
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
    
    $this->ticket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'reporter_student_id' => $this->studentRecord->id,
        'created_by_user_id' => $this->student->id,
    ]);
});

test('student can add comment to their own ticket', function () {
    Sanctum::actingAs($this->student, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/comments", [
        'body' => 'This is a test comment',
    ]);
    
    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'body',
                'created_at',
                'author',
            ],
            'message',
        ]);
    
    $this->assertDatabaseHas('ticket_comments', [
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->student->id,
        'body' => 'This is a test comment',
    ]);
});

test('warden can add comment to ticket in their hostel', function () {
    Sanctum::actingAs($this->warden, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/comments", [
        'body' => 'I will look into this issue',
    ]);
    
    $response->assertStatus(201);
    
    $this->assertDatabaseHas('ticket_comments', [
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->warden->id,
        'body' => 'I will look into this issue',
    ]);
});

test('can list comments for a ticket', function () {
    // Create comments
    $comment1 = TicketComment::factory()->create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->student->id,
        'body' => 'First comment',
    ]);
    
    $comment2 = TicketComment::factory()->create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->warden->id,
        'body' => 'Second comment',
    ]);
    
    Sanctum::actingAs($this->student, ['*']);
    
    $response = $this->getJson("/api/v1/tickets/{$this->ticket->id}/comments");
    
    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'body',
                    'created_at',
                    'author',
                ],
            ],
        ]);
});

test('comment creation validates required fields', function () {
    Sanctum::actingAs($this->student, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/comments", []);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

test('comment creation validates body length', function () {
    Sanctum::actingAs($this->student, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/comments", [
        'body' => str_repeat('a', 2001), // Exceeds 2000 character limit
    ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

test('comment can include attachments', function () {
    Sanctum::actingAs($this->student, ['*']);
    
    $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/comments", [
        'body' => 'Here are some photos of the issue',
        'attachments' => [
            'https://example.com/photo1.jpg',
            'https://example.com/photo2.jpg',
        ],
    ]);
    
    $response->assertStatus(201);
    
    $this->assertDatabaseHas('ticket_comments', [
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->student->id,
        'body' => 'Here are some photos of the issue',
    ]);
    
    $comment = TicketComment::where('ticket_id', $this->ticket->id)->first();
    expect($comment->attachments)->toHaveCount(2);
});
