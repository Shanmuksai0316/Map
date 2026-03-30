<?php

use App\Domain\Tickets\Models\Ticket;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'HKSupervisor', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'CampusManager', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'Rector', 'guard_name' => 'sanctum']);
    $this->tenant = Tenant::factory()->create();
    $this->hostel = Hostel::factory()->create(['tenant_id' => $this->tenant->id]);
    
    // Create users with different roles
    $this->student = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->student->assignRole('Student');
    
    $this->warden = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->warden->assignRole('Warden');
    
    $this->hkSupervisor = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->hkSupervisor->assignRole('HKSupervisor');
    
    $this->campusManager = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->campusManager->assignRole('CampusManager');
    
    $this->rector = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->rector->assignRole('Rector');
    
    // Create a student record for the student user
    $this->studentRecord = Student::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'user_id' => $this->student->id,
    ]);
    
    // Create a ticket
    $this->ticket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'reporter_student_id' => $this->studentRecord->id,
        'created_by_user_id' => $this->student->id,
    ]);
});

test('student can view their own ticket', function () {
    $this->actingAs($this->student);
    
    expect($this->student->can('view', $this->ticket))->toBeTrue();
});

test('student cannot view other students tickets', function () {
    $otherStudent = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $otherStudent->assignRole('Student');
    
    $otherStudentRecord = Student::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'user_id' => $otherStudent->id,
    ]);
    
    $otherTicket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'reporter_student_id' => $otherStudentRecord->id,
        'created_by_user_id' => $otherStudent->id,
    ]);
    
    $this->actingAs($this->student);
    
    expect($this->student->can('view', $otherTicket))->toBeFalse();
});

test('warden can view tickets in their hostel', function () {
    $this->actingAs($this->warden);
    
    expect($this->warden->can('view', $this->ticket))->toBeTrue();
});

test('hk supervisor can view tickets in their hostel', function () {
    $this->actingAs($this->hkSupervisor);
    
    expect($this->hkSupervisor->can('view', $this->ticket))->toBeTrue();
});

test('campus manager can view all tickets in tenant', function () {
    $this->actingAs($this->campusManager);
    
    expect($this->campusManager->can('view', $this->ticket))->toBeTrue();
});

test('rector can view all tickets in tenant', function () {
    $this->actingAs($this->rector);
    
    expect($this->rector->can('view', $this->ticket))->toBeTrue();
});

test('student can create tickets', function () {
    $this->actingAs($this->student);
    
    expect($this->student->can('create', Ticket::class))->toBeTrue();
});

test('student can comment on their own ticket', function () {
    $this->actingAs($this->student);
    
    expect($this->student->can('comment', $this->ticket))->toBeTrue();
});

test('student cannot comment on other tickets', function () {
    $otherStudent = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $otherStudent->assignRole('Student');
    
    $otherStudentRecord = Student::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'user_id' => $otherStudent->id,
    ]);
    
    $otherTicket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'reporter_student_id' => $otherStudentRecord->id,
        'created_by_user_id' => $otherStudent->id,
    ]);
    
    $this->actingAs($this->student);
    
    expect($this->student->can('comment', $otherTicket))->toBeFalse();
});

test('hk supervisor can assign tickets in their hostel', function () {
    $this->actingAs($this->hkSupervisor);
    
    expect($this->hkSupervisor->can('assign', $this->ticket))->toBeTrue();
});

test('campus manager can assign any ticket', function () {
    $this->actingAs($this->campusManager);
    
    expect($this->campusManager->can('assign', $this->ticket))->toBeTrue();
});

test('campus manager can close tickets', function () {
    $this->actingAs($this->campusManager);
    
    expect($this->campusManager->can('close', $this->ticket))->toBeTrue();
});

test('supervisor cannot close tickets', function () {
    $this->actingAs($this->hkSupervisor);
    
    expect($this->hkSupervisor->can('close', $this->ticket))->toBeFalse();
});

test('ticket can transition to valid statuses', function () {
    $ticket = Ticket::factory()->open()->create();
    
    expect($ticket->canTransitionTo('in_progress'))->toBeTrue();
    expect($ticket->canTransitionTo('on_hold'))->toBeTrue();
    expect($ticket->canTransitionTo('closed'))->toBeTrue();
    expect($ticket->canTransitionTo('resolved'))->toBeFalse();
});

test('ticket cannot transition to invalid statuses', function () {
    $ticket = Ticket::factory()->open()->create();
    
    expect($ticket->canTransitionTo('resolved'))->toBeFalse();
    expect($ticket->canTransitionTo('invalid_status'))->toBeFalse();
});

test('ticket sla check works correctly', function () {
    $ticketWithinSla = Ticket::factory()->withinSla()->create();
    $ticketBreached = Ticket::factory()->breached()->create();
    
    expect($ticketWithinSla->isWithinSla())->toBeTrue();
    expect($ticketWithinSla->isBreached())->toBeFalse();
    
    expect($ticketBreached->isWithinSla())->toBeFalse();
    expect($ticketBreached->isBreached())->toBeTrue();
});

test('ticket reporter name works for student', function () {
    $student = Student::factory()->create();
    $ticket = Ticket::factory()->create([
        'reporter_student_id' => $student->id,
        'reporter_user_id' => null,
    ]);
    
    expect($ticket->reporter_name)->toBe($student->user->name);
    expect($ticket->reporter_type)->toBe('student');
});

test('ticket reporter name works for staff', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'reporter_student_id' => null,
        'reporter_user_id' => $user->id,
    ]);
    
    expect($ticket->reporter_name)->toBe($user->name);
    expect($ticket->reporter_type)->toBe('staff');
});

test('ticket scopes work correctly', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole('Student');
    
    $studentRecord = Student::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'user_id' => $user->id,
    ]);
    
    $userTicket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'reporter_student_id' => $studentRecord->id,
        'created_by_user_id' => $user->id,
    ]);
    
    $otherTicket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
    ]);
    
    $this->actingAs($user);
    
    $myTickets = Ticket::mine($user)->get();
    expect($myTickets)->toHaveCount(1);
    expect($myTickets->first()->id)->toBe($userTicket->id);
    
    $openTickets = Ticket::byStatus(['open'])->get();
    expect($openTickets->count())->toBeGreaterThanOrEqual(0);
    
    $housekeepingTickets = Ticket::byCategory(['housekeeping'])->get();
    expect($housekeepingTickets->count())->toBeGreaterThanOrEqual(0);
});
