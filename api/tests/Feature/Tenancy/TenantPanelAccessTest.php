<?php

use App\Models\User;
use App\Models\Tenant;

it('campus manager panel requires tenancy init', function () {
    $u = User::factory()->create(['kind' => 'campus_manager']);
    $u->assignRole('Campus Manager');

    actingAs($u, 'web')->get('/campus-manager')->assertStatus(404);
});

it('campus manager can access their tenant panel when tenancy initialized', function () {
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 't1.example.test']);

    $u = User::factory()->create(['tenant_id' => $tenant->id, 'kind' => 'campus_manager']);
    $u->assignRole('Campus Manager');

    tenancy()->initialize($tenant);

    actingAs($u, 'web')->get('/campus-manager')->assertStatus(200);
});


