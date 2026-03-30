<?php

declare(strict_types=1);

use App\Models\Room;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Imports\RoomAllotmentImportService;
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

function actAsCampusManager(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->campusManager()->create(['tenant_id' => $tenant->id]);
    $user->assignRole('Campus Manager');
    Sanctum::actingAs($user);

    return compact('tenant', 'user');
}

it('flags validation issues during room allotment dry run', function (): void {
    $context = actAsCampusManager();

    $room = Room::factory()->create([
        'tenant_id' => $context['tenant']->id,
    ]);

    $bed = RoomBed::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'room_id' => $room->id,
        'hostel_id' => $room->hostel_id,
        'code' => 'A1',
    ]);

    Student::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'student_uid' => 'existing-001',
    ]);

    RoomAllocation::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'room_bed_id' => $bed->id,
        'student_id' => Student::factory()->create(['tenant_id' => $context['tenant']->id])->id,
        'is_active' => true,
    ]);

    $csvContent = <<<'CSV'
student_uid,hostel_code,room_no,bed_code,effective_from,note
missing-fields,,,,,
unknown,S1,101,A1,2025-09-01T10:00:00Z,
existing-001,S1,101,A1,invalid-date,
existing-001,S1,101,A1,2025-09-02T09:00:00Z,
CSV;

    $file = UploadedFile::fake()->createWithContent('room-allotments.csv', $csvContent);

    $service = new RoomAllotmentImportService;
    $job = $service->dryRun(['file' => $file]);

    $job->load('errors');

    expect($job->status)->toBe('DryRunErrors');
    expect($job->errors()->where('code', 'required')->count())->toBeGreaterThan(0);
    expect($job->errors()->where('code', 'unknown_student')->count())->toBeGreaterThan(0);
    expect($job->errors()->where('code', 'invalid_datetime')->count())->toBeGreaterThan(0);
    expect($job->errors()->where('code', 'duplicate_student_row')->count())->toBeGreaterThan(0);
    expect($job->errors()->whereIn('code', ['unknown_bed', 'bed_not_available'])->count())->toBeGreaterThan(0);
});
