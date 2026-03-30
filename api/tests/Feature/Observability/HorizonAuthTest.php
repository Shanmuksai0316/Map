<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'sanctum']);
});

test('non-admin user gets 403 for horizon access in production', function () {
    // Override environment to test production behavior
    app()->detectEnvironment(fn() => 'production');
    
    $user = User::factory()->create();
    $user->assignRole('Student');
    
    $this->actingAs($user, 'sanctum');
    
    $response = $this->get('/horizon');
    
    expect($response->status())->toBe(403);
});

test('super admin user gets 200 for horizon access in production', function () {
    // Override environment to test production behavior
    app()->detectEnvironment(fn() => 'production');
    
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    
    $this->actingAs($user, 'sanctum');
    
    $response = $this->get('/horizon');
    
    expect($response->status())->toBe(200);
});

test('unauthenticated user gets redirected for horizon access in production', function () {
    // Override environment to test production behavior
    app()->detectEnvironment(fn() => 'production');
    
    $response = $this->get('/horizon');
    
    // Should redirect to login or return 401/403
    expect($response->status())->toBeIn([302, 401, 403]);
});

test('horizon auth bypass works in testing environment', function () {
    // This test should pass because we're in testing environment
    $response = $this->get('/horizon');
    
    // In testing environment, Horizon auth should allow access
    expect($response->status())->toBe(200);
});
