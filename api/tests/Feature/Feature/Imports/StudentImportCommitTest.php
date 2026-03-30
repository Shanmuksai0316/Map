<?php

declare(strict_types=1);

use App\Jobs\ProcessStudentImportJob;
use App\Models\ImportJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actAsManagerForCommit(): Tenant
{
    Role::findOrCreate('Campus Manager');
    $tenant = Tenant::factory()->create();
    $user = User::factory()->campusManager()->create(['tenant_id' => $tenant->id]);
    $user->assignRole('Campus Manager');
    Sanctum::actingAs($user);

    return $tenant;
}

it('queues job when committing student import', function (): void {
    Queue::fake();
    Storage::fake();

    $tenant = actAsManagerForCommit();

    $job = ImportJob::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'DryRunOK',
        'filename' => Storage::put('imports', UploadedFile::fake()->create('students.csv', 10)),
    ]);

    $response = $this->postJson("/api/v1/admin/imports/students/{$job->id}/commit");

    $response->assertAccepted();

    Queue::assertPushed(ProcessStudentImportJob::class, function (ProcessStudentImportJob $queued) use ($job) {
        return $queued->importJob->is($job);
    });
});
