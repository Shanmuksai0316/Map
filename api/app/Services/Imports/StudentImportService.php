<?php

namespace App\Services\Imports;

use App\Jobs\ProcessStudentImportJob;
use App\Http\Middleware\SetPostgresSessionTenant;
use App\Models\ImportJob;
use App\Models\Student;
use App\Models\User;
use App\Support\Imports\StudentImportColumns;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelReader;
use Symfony\Component\HttpFoundation\Response;

class StudentImportService
{
    private const MAX_ROWS = 5000;
    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB

    private const ALLOWED_GENDERS = ['male', 'female', 'other'];

    private const ALLOWED_BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];

    public function dryRun(array $data): ImportJob
    {
        /** @var UploadedFile $file */
        $file = $data['file'];

        if ($file->getSize() > self::MAX_BYTES) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'File size exceeds 5 MB limit.');
        }

        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->store('imports');

        $job = ImportJob::query()->create([
            'tenant_id' => auth()->user()->tenant_id,
            'kind' => 'students',
            'status' => 'DryRun',
            'filename' => $path,
            'meta' => [
                'original_name' => $file->getClientOriginalName(),
                'hash' => $hash,
            ],
            'total_rows' => 0,
            'error_rows' => 0,
        ]);

        $this->evaluateSheet($job, $hash);

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

        ProcessStudentImportJob::dispatch($job);
    }

    private function evaluateSheet(ImportJob $job, string $hash): void
    {
        // Ensure tenant RLS is applied
        SetPostgresSessionTenant::setTenantSessionVariable($job->tenant_id);

        // Idempotency: block exact same file hash for this tenant/kind
        $duplicate = ImportJob::query()
            ->where('tenant_id', $job->tenant_id)
            ->where('kind', 'students')
            ->where('meta->hash', $hash)
            ->whereIn('status', ['DryRunOK', 'Completed'])
            ->first();

        $job->errors()->delete();

        if ($duplicate) {
            $job->errors()->create([
                'row_number' => 0,
                'code' => 'duplicate_file',
                'message' => sprintf(
                    'This file was already imported (job #%d on %s).',
                    $duplicate->id,
                    optional($duplicate->created_at)?->toDateTimeString()
                ),
                'row_snapshot' => null,
            ]);

            $job->forceFill([
                'total_rows' => 0,
                'error_rows' => 1,
                'status' => 'DryRunErrors',
            ])->save();

            return;
        }

        $path = Storage::disk('local')->path($job->filename);
        $reader = SimpleExcelReader::create($path);

        $headers = StudentImportColumns::normalizeHeaders($reader->getHeaders() ?? []);
        $missing = array_diff(StudentImportColumns::requiredHeaders(), $headers);

        foreach ($missing as $missingHeader) {
            $job->errors()->create([
                'row_number' => 0,
                'code' => 'missing_header',
                'message' => sprintf('Missing required header %s', $missingHeader),
                'row_snapshot' => null,
            ]);
        }

        if ($job->errors()->exists()) {
            $job->forceFill([
                'total_rows' => 0,
                'error_rows' => $job->errors()->count(),
                'status' => 'DryRunErrors',
            ])->save();

            return;
        }

        $rows = [];
        $totalRows = 0;

        $inFileSeen = [
            'student_uid' => [],
            'map_id' => [],
            'erp_number' => [],
            'email_address' => [],
            'mobile_number' => [],
        ];

        foreach ($reader->getRows() as $rowNumber => $row) {
            $totalRows++;

            if ($totalRows > self::MAX_ROWS) {
                $job->errors()->create([
                    'row_number' => 0,
                    'code' => 'row_limit_exceeded',
                    'message' => sprintf('Row limit exceeded. Max allowed is %d rows.', self::MAX_ROWS),
                    'row_snapshot' => null,
                ]);
                break;
            }

            $rowIndex = $rowNumber + 2; // header is row 1
            $normalized = $this->normalizeRow($row);
            $rows[] = ['index' => $rowIndex, 'data' => $normalized];

            // Required fields
            foreach (StudentImportColumns::requiredHeaders() as $field) {
                if (blank($normalized[$field] ?? null)) {
                    $job->errors()->create([
                        'row_number' => $rowIndex,
                        'code' => 'required',
                        'message' => sprintf('Field %s is required.', $field),
                        'row_snapshot' => $normalized,
                    ]);
                }
            }

            // Gender enum
            $gender = strtolower($normalized['gender'] ?? '');
            if ($gender && ! in_array($gender, self::ALLOWED_GENDERS, true)) {
                $job->errors()->create([
                    'row_number' => $rowIndex,
                    'code' => 'invalid_gender',
                    'message' => 'Gender must be one of: male, female, other.',
                    'row_snapshot' => $normalized,
                ]);
            }

            // Email format
            if (! empty($normalized['email_address']) && ! filter_var($normalized['email_address'], FILTER_VALIDATE_EMAIL)) {
                $job->errors()->create([
                    'row_number' => $rowIndex,
                    'code' => 'invalid_email',
                    'message' => 'Email Address is not valid.',
                    'row_snapshot' => $normalized,
                ]);
            }

            // Phone format
            if (! empty($normalized['mobile_number']) && ! preg_match('/^\+?[0-9]{10,15}$/', $normalized['mobile_number'])) {
                $job->errors()->create([
                    'row_number' => $rowIndex,
                    'code' => 'invalid_phone',
                    'message' => 'Mobile Number must be 10-15 digits, optionally prefixed with +.',
                    'row_snapshot' => $normalized,
                ]);
            }

            // Father/Mother/Guardian phone formats
            foreach (['father_mobile_number', 'mother_mobile_number', 'local_guardian_contact'] as $phoneField) {
                if (! empty($normalized[$phoneField]) && ! preg_match('/^\+?[0-9]{10,15}$/', $normalized[$phoneField])) {
                    $job->errors()->create([
                        'row_number' => $rowIndex,
                        'code' => 'invalid_phone',
                        'message' => sprintf('%s must be 10-15 digits, optionally prefixed with +.', $phoneField),
                        'row_snapshot' => $normalized,
                    ]);
                }
            }

            // Blood group enum
            if (! empty($normalized['blood_group']) && ! in_array(strtoupper($normalized['blood_group']), self::ALLOWED_BLOOD_GROUPS, true)) {
                $job->errors()->create([
                    'row_number' => $rowIndex,
                    'code' => 'invalid_blood_group',
                    'message' => 'Blood Group must be one of: '.implode(', ', self::ALLOWED_BLOOD_GROUPS).'.',
                    'row_snapshot' => $normalized,
                ]);
            }

            // Year of study enum
            if (! empty($normalized['year_of_study']) && ! in_array((string) $normalized['year_of_study'], ['1', '2', '3', '4', '5'], true)) {
                $job->errors()->create([
                    'row_number' => $rowIndex,
                    'code' => 'invalid_year_of_study',
                    'message' => 'Year of Study must be 1, 2, 3, 4, or 5.',
                    'row_snapshot' => $normalized,
                ]);
            }

            // Date of birth age check (>= 15 years)
            if (! empty($normalized['date_of_birth'])) {
                try {
                    $dob = \Illuminate\Support\Carbon::parse($normalized['date_of_birth']);
                    if ($dob->gt(now()->subYears(15))) {
                        $job->errors()->create([
                            'row_number' => $rowIndex,
                            'code' => 'invalid_date_of_birth',
                            'message' => 'Date of Birth must indicate age of at least 15 years.',
                            'row_snapshot' => $normalized,
                        ]);
                    }
                } catch (\Throwable) {
                    $job->errors()->create([
                        'row_number' => $rowIndex,
                        'code' => 'invalid_date_of_birth',
                        'message' => 'Date of Birth is not a valid date.',
                        'row_snapshot' => $normalized,
                    ]);
                }
            }

            // Track in-file duplicates
            foreach ($inFileSeen as $field => $seen) {
                $value = $normalized[$field] ?? null;
                if (blank($value)) {
                    continue;
                }

                if (isset($inFileSeen[$field][$value])) {
                    $job->errors()->create([
                        'row_number' => $rowIndex,
                        'code' => 'duplicate_in_file',
                        'message' => sprintf('%s value %s already used at row %d.', $field, $value, $inFileSeen[$field][$value]),
                        'row_snapshot' => $normalized,
                    ]);
                } else {
                    $inFileSeen[$field][$value] = $rowIndex;
                }
            }
        }

        // No rows read
        if ($totalRows === 0) {
            $job->errors()->create([
                'row_number' => 0,
                'code' => 'empty_file',
                'message' => 'The file has no data rows.',
                'row_snapshot' => null,
            ]);
        }

        // Database duplicate checks (tenant scoped)
        $this->checkDatabaseDuplicates($job, $rows, $inFileSeen);

        $job->forceFill([
            'total_rows' => $totalRows,
            'error_rows' => $job->errors()->count(),
            'status' => $job->errors()->exists() ? 'DryRunErrors' : 'DryRunOK',
        ])->save();
    }

    /**
     * Normalize row keys and trim string values.
     */
    private function normalizeRow(array $row): array
    {
        return StudentImportColumns::normalizeRow($row);
    }

    /**
     * Check for duplicates against the database (per tenant).
     */
    private function checkDatabaseDuplicates(ImportJob $job, array $rows, array $inFileSeen): void
    {
        if (empty($rows)) {
            return;
        }

        $tenantId = $job->tenant_id;
        $studentUids = [];
        $mapIds = [];
        $erpNumbers = [];
        $emails = [];
        $mobiles = [];

        foreach ($rows as $row) {
            $data = $row['data'];
            if (! empty($data['student_uid'])) {
                $studentUids[] = $data['student_uid'];
            }
            if (! empty($data['map_id'])) {
                $mapIds[] = $data['map_id'];
            }
            if (! empty($data['erp_number'])) {
                $erpNumbers[] = $data['erp_number'];
            }
            if (! empty($data['email_address'])) {
                $emails[] = $data['email_address'];
            }
            if (! empty($data['mobile_number'])) {
                $mobiles[] = $data['mobile_number'];
            }
        }

        // #region agent log
        \Log::info('StudentImportService.checkDatabaseDuplicates', [
            'sessionId' => '72ddcf',
            'runId' => 'pre-fix',
            'hypothesisId' => 'A',
            'tenantId' => $tenantId,
            'studentUidsCount' => count($studentUids),
            'mapIdsSample' => array_slice(array_values(array_unique($mapIds)), 0, 5),
        ]);
        // #endregion

        $existingStudentUids = Student::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('student_uid', $studentUids)
            ->pluck('student_uid')
            ->all();

        $existingMapIds = Student::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('map_student_id', $mapIds)
            ->pluck('map_student_id')
            ->all();

        $existingErp = Student::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('erp_number', $erpNumbers)
            ->pluck('erp_number')
            ->all();

        $existingEmails = User::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('email', $emails)
            ->pluck('email')
            ->all();

        $existingMobiles = User::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('phone', $mobiles)
            ->pluck('phone')
            ->all();

        foreach ($rows as $row) {
            $index = $row['index'];
            $data = $row['data'];

            if (! empty($data['student_uid']) && in_array($data['student_uid'], $existingStudentUids, true)) {
                $job->errors()->create([
                    'row_number' => $index,
                    'code' => 'existing_student_uid',
                    'message' => sprintf('Student UID %s already exists.', $data['student_uid']),
                    'row_snapshot' => $data,
                ]);
            }

            if (! empty($data['map_id']) && in_array($data['map_id'], $existingMapIds, true)) {
                $job->errors()->create([
                    'row_number' => $index,
                    'code' => 'existing_map_id',
                    'message' => sprintf('MAP ID %s already exists.', $data['map_id']),
                    'row_snapshot' => $data,
                ]);
            }

            if (! empty($data['erp_number']) && in_array($data['erp_number'], $existingErp, true)) {
                $job->errors()->create([
                    'row_number' => $index,
                    'code' => 'existing_erp_number',
                    'message' => sprintf('ERP Number %s already exists.', $data['erp_number']),
                    'row_snapshot' => $data,
                ]);
            }

            if (! empty($data['email_address']) && in_array($data['email_address'], $existingEmails, true)) {
                $job->errors()->create([
                    'row_number' => $index,
                    'code' => 'existing_email',
                    'message' => sprintf('Email %s already exists.', $data['email_address']),
                    'row_snapshot' => $data,
                ]);
            }

            if (! empty($data['mobile_number']) && in_array($data['mobile_number'], $existingMobiles, true)) {
                $job->errors()->create([
                    'row_number' => $index,
                    'code' => 'existing_mobile',
                    'message' => sprintf('Mobile %s already exists.', $data['mobile_number']),
                    'row_snapshot' => $data,
                ]);
            }
        }
    }
}
