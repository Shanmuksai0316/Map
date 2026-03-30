<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('authenticates a student with valid credentials', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->student()->create([
        'tenant_id' => $tenant->id,
        'email' => 'student@example.test',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'student@example.test',
        'password' => 'secret123',
        'device_name' => 'ios-simulator',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'tenant_id']]]);

    expect($user->tokens)->toHaveCount(1);
});

it('rejects invalid credentials', function (): void {
    User::factory()->student()->create([
        'email' => 'student@example.test',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'student@example.test',
        'password' => 'wrong-password',
        'device_name' => 'ios-simulator',
    ]);

    $response->assertUnauthorized();
});

it('validates required fields', function (): void {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

it('prevents non-students from logging in', function (): void {
    $user = User::factory()->rector()->create([
        'email' => 'rector@example.test',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'rector@example.test',
        'password' => 'secret123',
        'device_name' => 'ios-simulator',
    ]);

    $response->assertForbidden();

    expect($user->tokens)->toHaveCount(0);
});
