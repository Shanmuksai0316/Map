<?php

namespace App\Services\Imports;

use App\Jobs\ProcessRoomAllotmentImportJob;
use App\Models\ImportJob;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use Symfony\Component\HttpFoundation\Response;

class RoomAllotmentImportService
{
    public function dryRun(array $data): ImportJob
    {
        /** @var UploadedFile $file */
        $file = $data['file'];
        $path = $file->store('imports');

        $job = ImportJob::query()->create([
            'tenant_id' => auth()->user()->tenant_id,
            'kind' => 'room_allotments',
            'status' => 'DryRun',
            'filename' => $path,
            'meta' => [
                'original_name' => $file->getClientOriginalName(),
            ],
            'total_rows' => 0,
            'error_rows' => 0,
        ]);

        $this->evaluateCsv($job);

        return $job->fresh('errors');
    }

    public function commit(ImportJob $job): void
    {
        $tenantId = auth()->user()->tenant_id;

        if ($job->tenant_id !== $tenantId) {
            abort(Response::HTTP_FORBIDDEN, 'You cannot commit imports from another tenant.');
        }

        if ($job->status !== 'DryRunOK') {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Import job must pass dry-run before commit.');
        }

        $job->forceFill([
            'status' => 'Queued',
        ])->save();

        ProcessRoomAllotmentImportJob::dispatch($job);
    }

    private function evaluateCsv(ImportJob $job): void
    {
        $contents = Storage::get($job->filename);

        if ($contents === false) {
            abort(Response::HTTP_BAD_REQUEST, 'Unable to read uploaded file.');
        }

        $csv = Reader::createFromString($contents);
        $csv->setHeaderOffset(0);

        $job->errors()->delete();

        $requiredHeaders = ['student_uid', 'hostel_code', 'room_no', 'bed_code', 'effective_from'];
        $headers = array_map('strtolower', $csv->getHeader() ?? []);

        $missing = array_diff($requiredHeaders, $headers);

        foreach ($missing as $missingHeader) {
            $job->errors()->create([
                'row_number' => 0,
                'code' => 'missing_header',
                'message' => sprintf('Missing required header %s', $missingHeader),
                'row_snapshot' => null,
            ]);
        }

        $totalRows = 0;
        $seenStudents = [];
        $seenBeds = [];

        foreach ($csv->getRecords() as $rowNumber => $row) {
            $totalRows++;
            $rowIndex = $rowNumber + 2;
            $normalized = array_change_key_case($row, CASE_LOWER);
            $snapshot = Arr::only($normalized, array_merge($requiredHeaders, ['effective_to', 'note']));

            foreach ($requiredHeaders as $field) {
                if (empty($normalized[$field] ?? null)) {
                    $job->errors()->create([
                        'row_number' => $rowIndex,
                        'code' => 'required',
                        'message' => sprintf('Field %s is required.', $field),
                        'row_snapshot' => $snapshot,
                    ]);
                }
            }

            $studentUid = $normalized['student_uid'] ?? null;
            $student = null;

            if ($studentUid !== null) {
                // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
                $student = Student::query()
                    ->where('student_uid', $studentUid)
                    ->first();

                if (! $student) {
                    $job->errors()->create([
                        'row_number' => $rowIndex,
                        'code' => 'unknown_student',
                        'message' => sprintf('Student UID %s not found.', $studentUid),
                        'row_snapshot' => $snapshot,
                    ]);

                    continue;
                } elseif (isset($seenStudents[$student->id])) {
                    $job->errors()->create([
                        'row_number' => $rowIndex,
                        'code' => 'duplicate_student_row',
                        'message' => sprintf('Student UID %s appears multiple times in file (first at row %d).', $studentUid, $seenStudents[$student->id]),
                        'row_snapshot' => $snapshot,
                    ]);
                }
            }

            $bedCode = $normalized['bed_code'] ?? null;
            $roomNo = $normalized['room_no'] ?? null;
            $hostelCode = $normalized['hostel_code'] ?? null;
            $bed = null;

            if ($bedCode && $roomNo && $hostelCode) {
                // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
                $bed = RoomBed::query()
                    ->with(['blockedPeriods'])
                    ->where('code', $bedCode)
                    ->whereHas('room', function ($query) use ($roomNo, $hostelCode): void {
                        $query->where('number', $roomNo)
                            ->whereHas('hostel', fn ($hostelQuery) => $hostelQuery->where('code', $hostelCode));
                    })
                    ->first();

                if (! $bed) {
                    $job->errors()->create([
                        'row_number' => $rowIndex,
                        'code' => 'unknown_bed',
                        'message' => 'Bed reference is invalid for the supplied hostel/room.',
                        'row_snapshot' => $snapshot,
                    ]);
                } elseif (in_array($bed->status, ['blocked', 'maintenance'], true)) {
                    $job->errors()->create([
                        'row_number' => $rowIndex,
                        'code' => 'bed_not_available',
                        'message' => 'Bed is not available for allocation.',
                        'row_snapshot' => $snapshot,
                    ]);
                }
            }

            $effectiveFrom = $normalized['effective_from'] ?? null;
            $effectiveTo = $normalized['effective_to'] ?? null;
            $fromAt = null;
            $toAt = null;

            if ($effectiveFrom !== null) {
                try {
                    $fromAt = Carbon::parse($effectiveFrom);
                } catch (InvalidFormatException) {
                    $job->errors()->create([
                        'row_number' => $rowIndex,
                        'code' => 'invalid_datetime',
                        'message' => 'effective_from is not a valid date/time.',
                        'row_snapshot' => $snapshot,
                    ]);
                }
            }

            if ($effectiveTo !== null && $effectiveTo !== '') {
                try {
                    $toAt = Carbon::parse($effectiveTo);
                } catch (InvalidFormatException) {
                    $job->errors()->create([
                        'row_number' => $rowIndex,
                        'code' => 'invalid_datetime',
                        'message' => 'effective_to is not a valid date/time.',
                        'row_snapshot' => $snapshot,
                    ]);
                }
            }

            if ($fromAt && $toAt && $toAt->lt($fromAt)) {
                $job->errors()->create([
                    'row_number' => $rowIndex,
                    'code' => 'invalid_range',
                    'message' => 'effective_to must be after effective_from.',
                    'row_snapshot' => $snapshot,
                ]);
            }

            if ($student && ! isset($seenStudents[$student->id])) {
                $seenStudents[$student->id] = $rowIndex;

                // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
                if (RoomAllocation::query()
                    ->where('student_id', $student->id)
                    ->where('is_active', true)
                    ->exists()) {
                    $job->errors()->create([
                        'row_number' => $rowIndex,
                        'code' => 'student_already_allocated',
                        'message' => 'Student already has an active allocation.',
                        'row_snapshot' => $snapshot,
                    ]);
                }
            }

            if ($bed && ! isset($seenBeds[$bed->id])) {
                $isBlockedDuring = $bed->blockedPeriods
                    ->filter(fn ($blocked) => $fromAt && $blocked->effective_from <= $fromAt && (! $blocked->effective_to || $blocked->effective_to >= $fromAt))
                    ->isNotEmpty();

                if ($isBlockedDuring) {
                    $job->errors()->create([
                        'row_number' => $rowIndex,
                        'code' => 'bed_blocked',
                        'message' => 'Bed is blocked for the selected period.',
                        'row_snapshot' => $snapshot,
                    ]);
                }

                $seenBeds[$bed->id] = $rowIndex;
            }
        }

        $job->forceFill([
            'total_rows' => $totalRows,
            'error_rows' => $job->errors()->count(),
            'status' => $job->errors()->exists() ? 'DryRunErrors' : 'DryRunOK',
        ])->save();
    }
}
