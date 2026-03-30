<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Services\Imports\StudentImportService;
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

function actAsManager(): void
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->campusManager()->create(['tenant_id' => $tenant->id]);
    $user->assignRole('Campus Manager');
    Sanctum::actingAs($user);
}

it('captures missing headers and duplicates during dry run', function (): void {
    actAsManager();

    $csvContent = <<<'CSV'
student_uid,name
123,John Doe
123,Jane Doe
CSV;

    $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

    $service = new StudentImportService;
    $job = $service->dryRun(['file' => $file]);

    $job->load('errors');

    expect($job->status)->toBe('DryRunErrors');
    expect($job->errors()->where('code', 'missing_header')->count())->toBeGreaterThanOrEqual(1);
    expect($job->errors()->where('code', 'duplicate_student_uid')->count())->toBe(1);
    expect($job->errors()->where('code', 'required')->count())->toBeGreaterThanOrEqual(2);
    expect($job->errors()->where('code', 'invalid_gender')->count())->toBe(0);
    expect($job->errors()->where('code', 'invalid_phone')->count())->toBe(0);
    expect($job->errors()->where('code', 'existing_student_uid')->count())->toBe(0);
});
