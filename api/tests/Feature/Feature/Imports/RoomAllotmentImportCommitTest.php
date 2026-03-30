<?php

declare(strict_types=1);

use App\Jobs\ProcessRoomAllotmentImportJob;
use App\Models\ImportJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actAsCampusManagerForRoomImports(): Tenant
{
    Role::findOrCreate('Campus Manager');
    Role::findOrCreate('Import Manager');
    $tenant = Tenant::factory()->create();
    $manager = User::factory()->campusManager()->create(['tenant_id' => $tenant->id]);
    $manager->assignRole('Campus Manager');
    $manager->assignRole('Import Manager');
    Sanctum::actingAs($manager);

    return $tenant;
}

it('queues job when committing room allotment import', function (): void {
    Queue::fake();
    $tenant = actAsCampusManagerForRoomImports();

    $job = ImportJob::factory()->create([
        'tenant_id' => $tenant->id,
        'kind' => 'room_allotments',
        'status' => 'DryRunOK',
    ]);

    $response = $this->postJson("/api/v1/admin/imports/room-allotments/{$job->id}/commit");

    $response->assertAccepted();

    Queue::assertPushed(ProcessRoomAllotmentImportJob::class, fn (ProcessRoomAllotmentImportJob $queued): bool => $queued->importJob->is($job));
});
