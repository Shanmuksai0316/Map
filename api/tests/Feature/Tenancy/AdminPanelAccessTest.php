<?php

use App\Models\User;

it('super admin can access admin panel centrally', function () {
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    actingAs($user, 'web')
        ->get('/admin')
        ->assertStatus(200);
});

it('non super admin cannot access admin panel', function () {
    $user = User::factory()->create();

    actingAs($user, 'web')
        ->get('/admin')
        ->assertStatus(403);
});


