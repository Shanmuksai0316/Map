<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use Throwable;

class ProcessRoomAllotmentImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public ImportJob $importJob)
    {
        //
    }

    public function handle(): void
    {
        $job = $this->importJob->fresh();

        if (! $job || $job->status !== 'Queued') {
            return;
        }

        $job->forceFill([
            'status' => 'Processing',
        ])->save();

        try {
            $this->processCsv($job);

            $job->forceFill([
                'status' => 'Completed',
                'committed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            Log::error('Failed processing room allotment import', [
                'job_id' => $job->id,
                'error' => $exception->getMessage(),
            ]);

            $job->forceFill([
                'status' => 'Failed',
                'meta' => array_merge($job->meta ?? [], [
                    'error' => $exception->getMessage(),
                ]),
            ])->save();

            throw $exception;
        }
    }

    private function processCsv(ImportJob $job): void
    {
        $contents = Storage::get($job->filename);

        if ($contents === false) {
            throw new \RuntimeException('Unable to load import file for processing.');
        }

        $csv = Reader::createFromString($contents);
        $csv->setHeaderOffset(0);

        $processed = 0;
        $inserted = 0;
        $updated = 0;

        DB::transaction(function () use ($csv, $job, &$processed, &$inserted, &$updated): void {
            foreach ($csv->getRecords() as $rowNumber => $row) {
                $processed++;
                $normalized = array_change_key_case($row, CASE_LOWER);

                $studentUid = Arr::get($normalized, 'student_uid');
                $hostelCode = Arr::get($normalized, 'hostel_code');
                $roomNo = Arr::get($normalized, 'room_no');
                $bedCode = Arr::get($normalized, 'bed_code');
                $effectiveFrom = Arr::get($normalized, 'effective_from');
                $effectiveTo = Arr::get($normalized, 'effective_to');
                $note = Arr::get($normalized, 'note');

                // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
                $student = Student::query()
                    ->where('student_uid', $studentUid)
                    ->first();

                // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
                $bed = RoomBed::query()
                    ->where('code', $bedCode)
                    ->whereHas('room', function ($query) use ($roomNo, $hostelCode): void {
                        $query->where('number', $roomNo)
                            ->whereHas('hostel', fn ($hostelQuery) => $hostelQuery->where('code', $hostelCode));
                    })
                    ->lockForUpdate()
                    ->first();

                if (! $student || ! $bed) {
                    continue;
                }

                $fromAt = $effectiveFrom ? Carbon::parse($effectiveFrom) : now();
                $toAt = $effectiveTo ? Carbon::parse($effectiveTo) : null;

                // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
                $previousAllocations = RoomAllocation::query()
                    ->where('student_id', $student->id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->get();

                foreach ($previousAllocations as $allocation) {
                    $allocation->forceFill([
                        'is_active' => false,
                        'effective_to' => $fromAt,
                    ])->save();

                    $allocation->roomBed->forceFill([
                        'status' => 'available',
                        'released_at' => $fromAt,
                    ])->save();
                }

                // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
                $existingAllocation = RoomAllocation::query()
                    ->where('room_bed_id', $bed->id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if ($existingAllocation) {
                    $existingAllocation->forceFill([
                        'is_active' => false,
                        'effective_to' => $fromAt,
                    ])->save();

                    $bed->forceFill([
                        'status' => 'available',
                        'released_at' => $fromAt,
                    ])->save();
                }

                $periodMonths = config('checkouts.default_period_months', 18);
                $expectedCheckoutAt = $toAt === null ? $fromAt->copy()->addMonths($periodMonths) : null;
                $checkoutStatus = $toAt === null ? 'pending' : 'completed';

                // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
                $allocation = RoomAllocation::query()->create([
                    'student_id' => $student->id,
                    'room_bed_id' => $bed->id,
                    'hostel_id' => $bed->hostel_id,
                    'effective_from' => $fromAt,
                    'effective_to' => $toAt,
                    'is_active' => $toAt === null,
                    'note' => $note,
                    'expected_checkout_at' => $expectedCheckoutAt,
                    'checkout_status' => $checkoutStatus,
                ]);

                $bed->forceFill([
                    'status' => $toAt ? 'available' : 'occupied',
                    'occupied_at' => $fromAt,
                    'released_at' => $toAt,
                ])->save();

                if ($allocation->wasRecentlyCreated) {
                    $inserted++;
                } elseif ($allocation->wasChanged()) {
                    $updated++;
                }
            }
        });

        $job->forceFill([
            'processed_rows' => $processed,
            'inserted_rows' => $inserted,
            'updated_rows' => $updated,
        ])->save();
    }
}
