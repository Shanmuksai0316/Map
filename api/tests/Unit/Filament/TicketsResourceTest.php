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
    // Clear permission cache
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    
    // Create roles
    Role::firstOrCreate(['name' => 'CampusManager', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'HKSupervisor', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'sanctum']);
    
    $this->tenant = Tenant::factory()->create();
    $this->hostel = Hostel::factory()->create(['tenant_id' => $this->tenant->id]);
    
    $this->campusManager = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->campusManager->assignRole('CampusManager');
    
    $this->hkSupervisor = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->hkSupervisor->assignRole('HKSupervisor');
    
    $this->student = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->student->assignRole('Student');
    
    $this->studentRecord = Student::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'user_id' => $this->student->id,
    ]);
});

test('ticket resource file exists', function () {
    $resourcePath = app_path('Filament/CampusManager/Resources/TicketResource.php');
    expect(file_exists($resourcePath))->toBeTrue();
});

test('ticket resource pages exist', function () {
    $listPagePath = app_path('Filament/CampusManager/Resources/TicketResource/Pages/ListTickets.php');
    $viewPagePath = app_path('Filament/CampusManager/Resources/TicketResource/Pages/ViewTicket.php');
    
    expect(file_exists($listPagePath))->toBeTrue();
    expect(file_exists($viewPagePath))->toBeTrue();
});

test('ticket model has required methods for filament', function () {
    $ticket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
    ]);
    
    // Test that the model has the methods needed for Filament
    expect(method_exists($ticket, 'getAllowedTransitions'))->toBeTrue();
    expect(method_exists($ticket, 'isWithinSla'))->toBeTrue();
    expect(method_exists($ticket, 'isBreached'))->toBeTrue();
    
    // Test the methods work
    expect($ticket->getAllowedTransitions())->toBeArray();
    expect($ticket->isWithinSla())->toBeBool();
    expect($ticket->isBreached())->toBeBool();
});

test('ticket model relationships work correctly', function () {
    $ticket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'assignee_user_id' => $this->hkSupervisor->id,
        'reporter_student_id' => $this->studentRecord->id,
    ]);
    
    // Test relationships
    expect($ticket->hostel)->not->toBeNull();
    expect($ticket->assigneeUser)->not->toBeNull();
    expect($ticket->reporterStudent)->not->toBeNull();
    expect($ticket->comments)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

test('ticket policy methods exist and work', function () {
    $ticket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
    ]);
    
    // Test that policy methods exist
    expect($this->campusManager->can('view', $ticket))->toBeTrue();
    expect($this->campusManager->can('assign', $ticket))->toBeTrue();
    expect($this->campusManager->can('transition', $ticket))->toBeTrue();
    expect($this->campusManager->can('comment', $ticket))->toBeTrue();
    
    // Test supervisor permissions
    $this->hkSupervisor->update(['hostel_ids' => [$this->hostel->id]]);
    expect($this->hkSupervisor->can('view', $ticket))->toBeTrue();
    expect($this->hkSupervisor->can('assign', $ticket))->toBeTrue();
    expect($this->hkSupervisor->can('transition', $ticket))->toBeTrue();
    expect($this->hkSupervisor->can('comment', $ticket))->toBeTrue();
});
