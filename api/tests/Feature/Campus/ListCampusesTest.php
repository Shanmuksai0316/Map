<?php

use App\Models\Campus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists campuses for the authenticated student tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $campuses = Campus::factory()->count(2)->create(['tenant_id' => $tenant->id]);

    $student = User::factory()->student()->create([
        'tenant_id' => $tenant->id,
        'email' => 'student@example.test',
        'password' => Hash::make('secret123'),
    ]);

    Sanctum::actingAs($student);

    $response = $this->getJson('/api/v1/campuses');

    $response->assertOk()
        ->assertJsonCount($campuses->count(), 'data');
});

it('does not return campuses from other tenants', function (): void {
    $tenant = Tenant::factory()->create();
    Campus::factory()->create(['tenant_id' => $tenant->id]);

    $otherTenant = Tenant::factory()->create();
    $otherStudent = User::factory()->student()->create(['tenant_id' => $otherTenant->id]);

    Sanctum::actingAs($otherStudent);

    $response = $this->getJson('/api/v1/campuses');

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});
