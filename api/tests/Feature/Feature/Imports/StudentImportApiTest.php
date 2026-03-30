<?php

declare(strict_types=1);

use App\Models\ImportJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake();
    Role::findOrCreate('Campus Manager');
});

function actingAsCampusManagerWithTenant(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->campusManager()->create(['tenant_id' => $tenant->id]);
    $user->assignRole('Campus Manager');

    Sanctum::actingAs($user);

    return compact('tenant', 'user');
}

it('starts student import dry run', function (): void {
    actingAsCampusManagerWithTenant();

    $file = UploadedFile::fake()->createWithContent('students.csv', "student_uid,name\n123,John Doe");

    $response = $this->postJson('/api/v1/admin/imports/students/dry-run', [
        'file' => $file,
    ]);

    $response->assertCreated();
    $jobId = $response->json('data.id');

    expect(ImportJob::query()->find($jobId))->not()->toBeNull();
});
