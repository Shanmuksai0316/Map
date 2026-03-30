<?php

use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns hostels for the authenticated student tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $campus = Campus::factory()->create(['tenant_id' => $tenant->id]);
    $hostels = Hostel::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'campus_id' => $campus->id,
    ]);

    $student = User::factory()->student()->create([
        'tenant_id' => $tenant->id,
        'email' => 'student@example.test',
        'password' => Hash::make('secret123'),
    ]);

    Sanctum::actingAs($student);

    $response = $this->getJson('/api/v1/hostels');

    $response->assertOk()
        ->assertJsonCount($hostels->count(), 'data')
        ->assertJsonPath('data.0.tenant_id', (string) $tenant->id);
});

it('filters by campus when provided', function (): void {
    $tenant = Tenant::factory()->create();
    $primaryCampus = Campus::factory()->create(['tenant_id' => $tenant->id]);
    $otherCampus = Campus::factory()->create(['tenant_id' => $tenant->id]);

    $matching = Hostel::factory()->create([
        'tenant_id' => $tenant->id,
        'campus_id' => $primaryCampus->id,
    ]);
    Hostel::factory()->create([
        'tenant_id' => $tenant->id,
        'campus_id' => $otherCampus->id,
    ]);

    $student = User::factory()->student()->create(['tenant_id' => $tenant->id]);
    Sanctum::actingAs($student);

    $response = $this->getJson('/api/v1/hostels?campus_id='.$primaryCampus->id);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', (string) $matching->id);
});

it('does not leak hostels from other tenants', function (): void {
    $tenant = Tenant::factory()->create();
    $campus = Campus::factory()->create(['tenant_id' => $tenant->id]);
    Hostel::factory()->create([
        'tenant_id' => $tenant->id,
        'campus_id' => $campus->id,
    ]);

    $otherTenant = Tenant::factory()->create();
    $otherStudent = User::factory()->student()->create(['tenant_id' => $otherTenant->id]);

    Sanctum::actingAs($otherStudent);

    $response = $this->getJson('/api/v1/hostels');

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});
