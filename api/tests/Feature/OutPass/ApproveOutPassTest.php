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

function seedOutPass(): array
{
    $tenant = Tenant::factory()->create();
    $campus = Campus::factory()->create(['tenant_id' => $tenant->id]);
    $hostel = Hostel::factory()->create([
        'tenant_id' => $tenant->id,
        'campus_id' => $campus->id,
    ]);

    $studentUser = User::factory()->student()->create(['tenant_id' => $tenant->id]);
    $student = $studentUser->student;

    $outPass = OutPass::factory()->create([
        'tenant_id' => $tenant->id,
        'student_id' => $student->id,
        'hostel_id' => $hostel->id,
        'status' => OutPassStatus::PENDING,
    ]);

    return [$tenant, $hostel, $studentUser, $outPass];
}

it('allows rector to approve an out pass', function (): void {
    [$tenant, $hostel, $studentUser, $outPass] = seedOutPass();

    $rector = User::factory()->rector()->create(['tenant_id' => $tenant->id]);

    Sanctum::actingAs($rector);
    expect(auth()->id())->toBe($rector->id);
    expect(auth()->user()->kind)->toBe('Rector');
    expect(Gate::allows('update', $outPass))->toBeTrue();

    $response = $this->patchJson("/api/v1/outpasses/{$outPass->id}", ['status' => 'approved']);

    $response->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($outPass->refresh()->status)->toBe(OutPassStatus::APPROVED);
});

it('prevents student from approving', function (): void {
    [$tenant, $hostel, $studentUser, $outPass] = seedOutPass();

    Sanctum::actingAs($studentUser);

    $this->patchJson("/api/v1/outpasses/{$outPass->id}", ['status' => 'approved'])
        ->assertForbidden();

    expect($outPass->refresh()->status)->toBe(OutPassStatus::PENDING);
});
