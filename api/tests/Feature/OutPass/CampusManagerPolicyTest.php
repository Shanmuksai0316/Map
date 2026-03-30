<?php

use App\Enums\OutPassStatus;
use App\Models\Campus;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function seedOutPassForCampusManager(): array
{
    $tenant = Tenant::factory()->create();
    $campus = Campus::factory()->create(['tenant_id' => $tenant->id]);
    $hostel = Hostel::factory()->create([
        'tenant_id' => $tenant->id,
        'campus_id' => $campus->id,
    ]);

    $student = User::factory()->student()->create(['tenant_id' => $tenant->id]);

    $outPass = OutPass::factory()->create([
        'tenant_id' => $tenant->id,
        'student_id' => $student->student->id,
        'hostel_id' => $hostel->id,
        'status' => OutPassStatus::PENDING,
    ]);

    return [$tenant, $campus, $hostel, $student, $outPass];
}

it('allows campus manager to view and update pending out passes', function (): void {
    [$tenant, $campus, $hostel, $student, $outPass] = seedOutPassForCampusManager();

    $manager = User::factory()->campusManager()->create(['tenant_id' => $tenant->id]);

    Sanctum::actingAs($manager);

    expect(Gate::allows('viewAny', OutPass::class))->toBeTrue();
    expect(Gate::allows('view', $outPass))->toBeTrue();
    expect(Gate::allows('update', $outPass))->toBeTrue();

    $this->patchJson("/api/v1/outpasses/{$outPass->id}", ['status' => 'approved'])
        ->assertOk();

    expect($outPass->refresh()->status)->toBe(OutPassStatus::APPROVED);
});

it('prevents campus manager from acting on another tenant out pass', function (): void {
    [$tenant, $campus, $hostel, $student, $outPass] = seedOutPassForCampusManager();

    $otherTenant = Tenant::factory()->create();
    $manager = User::factory()->campusManager()->create(['tenant_id' => $otherTenant->id]);

    Sanctum::actingAs($manager);

    expect(Gate::allows('view', $outPass))->toBeFalse();
    expect(Gate::allows('update', $outPass))->toBeFalse();

    $this->patchJson("/api/v1/outpasses/{$outPass->id}", ['status' => 'approved'])
        ->assertForbidden();

    expect($outPass->refresh()->status)->toBe(OutPassStatus::PENDING);
});
