<?php

use App\Enums\OutPassStatus;
use App\Jobs\ExportOutPassesJob;
use App\Models\Campus;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Domain\OutPass\OutPassExport;
use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function seedOutPassesForExport(): array
{
    $tenant = Tenant::factory()->create();
    $campus = Campus::factory()->create(['tenant_id' => $tenant->id]);
    $hostel = Hostel::factory()->create([
        'tenant_id' => $tenant->id,
        'campus_id' => $campus->id,
    ]);

    $studentUser = User::factory()->student()->create(['tenant_id' => $tenant->id]);
    $student = $studentUser->student;

    OutPass::factory()->create([
        'tenant_id' => $tenant->id,
        'student_id' => $student->id,
        'hostel_id' => $hostel->id,
        'status' => OutPassStatus::PENDING,
    ]);

    $manager = User::factory()->campusManager()->create(['tenant_id' => $tenant->id]);

    return [$tenant, $manager];
}

it('queues an export job with filters', function (): void {
    [$tenant, $manager] = seedOutPassesForExport();

    Bus::fake();

    Sanctum::actingAs($manager);

    $response = $this->postJson('/api/v1/outpasses/export', [
        'status' => OutPassStatus::PENDING->value,
        'from' => now()->subDay()->toISOString(),
        'to' => now()->toISOString(),
    ]);

    $response->assertAccepted()
        ->assertJsonPath('data.status', OutPassExport::STATUS_PENDING);

    $export = OutPassExport::query()->where('tenant_id', $tenant->id)->first();

    Bus::assertDispatched(ExportOutPassesJob::class, function (ExportOutPassesJob $job) use ($export): bool {
        return $job->exportId === $export->id;
    });
});

it('prevents viewing exports from another tenant', function (): void {
    [$tenant, $manager] = seedOutPassesForExport();

    $export = OutPassExport::factory()->create([
        'tenant_id' => $tenant->id,
        'requested_by' => $manager->id,
        'status' => OutPassExport::STATUS_COMPLETE,
    ]);

    $otherTenant = Tenant::factory()->create();
    $otherManager = User::factory()->campusManager()->create(['tenant_id' => $otherTenant->id]);

    Sanctum::actingAs($otherManager);

    $this->getJson("/api/v1/outpasses/export/{$export->id}")
        ->assertForbidden();
});
