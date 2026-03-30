<?php

namespace App\Jobs;

use App\Events\StudentActivated;
use App\Http\Middleware\SetPostgresSessionTenant;
use App\Models\ImportJob;
use App\Models\Student;
use App\Models\User;
use App\Support\Imports\StudentImportColumns;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelReader;
use Throwable;

class ProcessStudentImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const IMPORTED_STUDENT_PREVIEW_LIMIT = 100;

    /** @var list<string>|null */
    private ?array $studentTableColumns = null;

    public function __construct(public ImportJob $importJob) {}

    public function handle(): void
    {
        $job = $this->importJob->fresh();

        if (! $job || $job->status === 'Completed') {
            return;
        }

        $job->forceFill(['status' => 'Processing'])->save();

        try {
            $this->processSheet($job);

            $job->forceFill([
                'status' => 'Completed',
                'committed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $job->forceFill([
                'status' => 'Failed',
                'meta' => array_merge($job->meta ?? [], [
                    'error' => $exception->getMessage(),
                ]),
            ])->save();

            throw $exception;
        }
    }

    private function processSheet(ImportJob $job): void
    {
        // Set tenant session for RLS
        SetPostgresSessionTenant::setTenantSessionVariable($job->tenant_id);

        $path = Storage::disk('local')->path($job->filename);
        $reader = SimpleExcelReader::create($path);

        $inserted = 0;
        $importedStudentsPreview = [];

        DB::transaction(function () use ($reader, $job, &$inserted, &$importedStudentsPreview): void {
            foreach ($reader->getRows() as $row) {
                $data = $this->normalizeRow($row);

                // Minimal safety: required fields should already be validated in dry-run
                foreach (StudentImportColumns::requiredHeaders() as $required) {
                    if (blank($data[$required] ?? null)) {
                        throw new \RuntimeException(sprintf('Row missing required field %s after dry-run.', $required));
                    }
                }

                $studentUid = $data['student_uid'] ?? null;
                if (blank($studentUid)) {
                    $studentUid = Student::generateStudentUid($job->tenant_id);
                }

                $mapId = $data['map_id'] ?? null;
                if (blank($mapId)) {
                    $mapId = Student::generateMapStudentId($job->tenant_id);
                }

                $dob = null;
                if (! empty($data['date_of_birth'])) {
                    $dob = \Illuminate\Support\Carbon::parse($data['date_of_birth']);
                }

                $user = User::create([
                    'tenant_id' => $job->tenant_id,
                    'name' => $data['full_name'],
                    'email' => $data['email_address'],
                    'phone' => $data['mobile_number'],
                    'gender' => strtolower($data['gender'] ?? ''),
                    'dob' => $dob,
                    'password' => Hash::make('Student@123'),
                    'kind' => 'student',
                    'is_active' => false,
                ]);

                $user->assignRole('Student');

                $student = Student::query()->forceCreate(
                    $this->buildStudentPayload(
                        data: $data,
                        job: $job,
                        user: $user,
                        studentUid: $studentUid,
                        mapId: $mapId,
                        dob: $dob,
                    )
                );

                event(new StudentActivated($student));
                $inserted++;

                if (count($importedStudentsPreview) < self::IMPORTED_STUDENT_PREVIEW_LIMIT) {
                    $importedStudentsPreview[] = [
                        'student_id' => $student->id,
                        'student_name' => $data['full_name'] ?? null,
                        'student_uid' => $studentUid,
                        'map_student_id' => $mapId,
                        'erp_number' => $data['erp_number'] ?? null,
                        'email' => $data['email_address'] ?? null,
                    ];
                }
            }
        });

        if ($inserted === 0) {
            $previewNote = 'No students were imported in this job.';
        } elseif ($inserted > self::IMPORTED_STUDENT_PREVIEW_LIMIT) {
            $previewNote = sprintf(
                'Showing first %d of %d imported students.',
                self::IMPORTED_STUDENT_PREVIEW_LIMIT,
                $inserted
            );
        } else {
            $previewNote = sprintf('Showing all %d imported students.', $inserted);
        }

        $job->forceFill([
            'inserted_rows' => $inserted,
            'updated_rows' => 0,
            'processed_rows' => $inserted,
            'meta' => array_merge($job->meta ?? [], [
                'inserted' => $inserted,
                'imported_students' => $importedStudentsPreview,
                'imported_students_preview_note' => $previewNote,
                'imported_students_total_count' => $inserted,
            ]),
        ])->save();
    }

    /**
     * Normalize row keys to snake_case headers and trim values.
     */
    private function normalizeRow(array $row): array
    {
        return StudentImportColumns::normalizeRow($row);
    }

    /**
     * Build a student payload that works with both legacy and new student schemas.
     */
    private function buildStudentPayload(
        array $data,
        ImportJob $job,
        User $user,
        string $studentUid,
        string $mapId,
        ?\Illuminate\Support\Carbon $dob
    ): array {
        $payload = [
            'tenant_id' => $job->tenant_id,
            'user_id' => $user->id,
            'map_student_id' => $mapId,
            'erp_number' => $data['erp_number'] ?? null,
            'year_of_study' => $data['year_of_study'] ?? null,
            'father_name' => $data['father_name'] ?? null,
            'father_mobile_number' => $data['father_mobile_number'] ?? null,
            'mother_name' => $data['mother_name'] ?? null,
            'mother_mobile_number' => $data['mother_mobile_number'] ?? null,
            'medical_information' => $data['medical_information'] ?? null,
        ];

        if ($this->hasStudentColumn('full_name')) {
            $payload['full_name'] = $data['full_name'];
        }

        if ($this->hasStudentColumn('student_uid')) {
            $payload['student_uid'] = $studentUid;
        }

        if ($this->hasStudentColumn('date_of_birth')) {
            $payload['date_of_birth'] = $dob;
        }

        if ($this->hasStudentColumn('gender')) {
            $payload['gender'] = strtolower($data['gender'] ?? '');
        }

        if ($this->hasStudentColumn('department')) {
            $payload['department'] = $data['department'] ?? null;
        }

        if ($this->hasStudentColumn('program')) {
            $payload['program'] = $data['department'] ?? null;
        }

        if ($this->hasStudentColumn('roll_no')) {
            $payload['roll_no'] = $data['erp_number'] ?? $studentUid;
        }

        if ($this->hasStudentColumn('mobile_number')) {
            $payload['mobile_number'] = $data['mobile_number'];
        }

        if ($this->hasStudentColumn('email_address')) {
            $payload['email_address'] = $data['email_address'];
        }

        if ($this->hasStudentColumn('local_guardian_name')) {
            $payload['local_guardian_name'] = $data['local_guardian_name'] ?? null;
        }

        if ($this->hasStudentColumn('local_guardian_contact')) {
            $payload['local_guardian_contact'] = $data['local_guardian_contact'] ?? null;
        }

        if ($this->hasStudentColumn('local_relationship')) {
            $payload['local_relationship'] = $data['local_relationship'] ?? null;
        }

        if ($this->hasStudentColumn('local_address')) {
            $payload['local_address'] = $data['local_address'] ?? null;
        }

        if ($this->hasStudentColumn('blood_group')) {
            $payload['blood_group'] = $data['blood_group'] ?? null;
        }

        if ($this->hasStudentColumn('guardian')) {
            $guardian = array_filter([
                'father_name' => $data['father_name'] ?? null,
                'father_phone' => $data['father_mobile_number'] ?? null,
                'mother_name' => $data['mother_name'] ?? null,
                'mother_phone' => $data['mother_mobile_number'] ?? null,
                'local_guardian_name' => $data['local_guardian_name'] ?? null,
                'local_guardian_phone' => $data['local_guardian_contact'] ?? null,
                'local_guardian_relationship' => $data['local_relationship'] ?? null,
                'local_guardian_address' => $data['local_address'] ?? null,
            ], static fn ($value) => $value !== null && $value !== '');

            $payload['guardian'] = $guardian ?: null;
        }

        return $payload;
    }

    private function hasStudentColumn(string $column): bool
    {
        return in_array($column, $this->studentColumns(), true);
    }

    /**
     * @return list<string>
     */
    private function studentColumns(): array
    {
        if ($this->studentTableColumns === null) {
            $this->studentTableColumns = Schema::getColumnListing((new Student())->getTable());
        }

        return $this->studentTableColumns;
    }
}
