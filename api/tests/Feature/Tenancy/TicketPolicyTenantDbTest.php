<?php

use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Stancl\Tenancy\Database\Models\Tenant;

it('ticket policy works without tenant_id column in tenant DB', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole('CampusManager');

    $ticket = Ticket::create([
        'title' => 'Door broken',
        'description' => 'G-12 hinge issue',
        'hostel_id' => 1,
        'status' => 'open',
        'priority' => 'low',
    ]);

    expect($user->can('view', $ticket))->toBeTrue();
    expect($user->can('reopen', $ticket))->toBeTrue();
});

