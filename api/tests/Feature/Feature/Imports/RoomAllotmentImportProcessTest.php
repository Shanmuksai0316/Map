<?php

declare(strict_types=1);

use App\Jobs\ProcessRoomAllotmentImportJob;
use App\Models\ImportJob;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Writer;

uses(RefreshDatabase::class);

it('applies allocations and updates metrics', function (): void {
    $tenant = Tenant::factory()->create();

    $roomBed = RoomBed::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $student = Student::factory()->create([
        'tenant_id' => $tenant->id,
        'student_uid' => 'stu-001',
    ]);

    $csv = Writer::createFromString('student_uid,hostel_code,room_no,bed_code,effective_from' . PHP_EOL);

    $csv->insertOne([
        $student->student_uid,
        $roomBed->room->hostel->code,
        $roomBed->room->number,
        $roomBed->code,
        now()->toIso8601String(),
    ]);

    Storage::fake();
    $path = 'imports/' . Str::random(12) . '.csv';
    Storage::put($path, $csv->getContent());

    $job = ImportJob::factory()->create([
        'tenant_id' => $tenant->id,
        'kind' => 'room_allotments',
        'status' => 'Queued',
        'filename' => $path,
    ]);

    $queuedJob = new ProcessRoomAllotmentImportJob($job);
    $queuedJob->handle();

    $job->refresh();
    $roomBed->refresh();

    expect($job->status)->toBe('Completed');
    expect($job->processed_rows)->toBe(1);
    expect($job->inserted_rows)->toBe(1);
    expect($job->updated_rows)->toBe(0);

    $allocation = RoomAllocation::where('room_bed_id', $roomBed->id)->first();
    expect($allocation)->not()->toBeNull();
    expect($allocation->is_active)->toBeTrue();
    expect($roomBed->status)->toBe('occupied');
});
