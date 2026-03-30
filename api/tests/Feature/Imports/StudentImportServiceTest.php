<?php

declare(strict_types=1);

namespace Tests\Feature\Imports;

use App\Jobs\ProcessStudentImportJob;
use App\Models\ImportJob;
use App\Models\Student;
use App\Models\User;
use App\Services\Imports\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Tests\TestCase;

class StudentImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $headers = [
        'student_uid',
        'full_name',
        'email_address',
        'mobile_number',
        'gender',
        'date_of_birth',
        'map_id',
        'erp_number',
        'department',
        'year_of_study',
        'father_name',
        'father_mobile_number',
        'mother_name',
        'mother_mobile_number',
        'local_guardian_name',
        'local_guardian_contact',
        'local_relationship',
        'local_address',
        'blood_group',
        'medical_information',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist for student creation
        Role::findOrCreate('Student');

        // Auth user in tenant context
        $user = User::factory()->create([
            'tenant_id' => 'tenant-1',
        ]);

        Auth::login($user);

        // Use a fake local disk so imports stay in-memory
        Storage::fake('local');
    }

    public function test_dry_run_happy_path_passes_validation(): void
    {
        $file = $this->makeXlsx([
            [
                'student_uid' => 'STD-1',
                'full_name' => 'Alex Johnson',
                'email_address' => 'alex@example.com',
                'mobile_number' => '+911234567890',
                'gender' => 'male',
                'date_of_birth' => '2000-01-01',
                'map_id' => 'MAP-1',
                'erp_number' => 'ERP-1',
                'department' => 'CS',
                'year_of_study' => '1',
                'blood_group' => 'A+',
            ],
        ]);

        $job = app(StudentImportService::class)->dryRun(['file' => $file]);

        $job->refresh();

        $this->assertSame('DryRunOK', $job->status);
        $this->assertSame(1, $job->total_rows);
        $this->assertSame(0, $job->error_rows);
        $this->assertCount(0, $job->errors);
    }

    public function test_dry_run_flags_missing_required_fields(): void
    {
        $file = $this->makeXlsx([
            [
                'student_uid' => 'STD-2',
                'full_name' => '', // required
                'email_address' => 'bad-email', // invalid format
                'mobile_number' => '123', // invalid phone
                'gender' => 'unknown', // invalid enum
            ],
        ]);

        $job = app(StudentImportService::class)->dryRun(['file' => $file])->load('errors');

        $this->assertSame('DryRunErrors', $job->status);
        $this->assertGreaterThanOrEqual(4, $job->errors->count());
        $this->assertTrue(
            $job->errors->pluck('code')->contains(fn ($code) => in_array($code, [
                'required',
                'invalid_email',
                'invalid_phone',
                'invalid_gender',
            ], true))
        );
    }

    public function test_commit_creates_students_and_users(): void
    {
        $file = $this->makeXlsx([
            [
                'student_uid' => 'STD-3',
                'full_name' => 'Taylor Swift',
                'email_address' => 'taylor@example.com',
                'mobile_number' => '+919999888877',
                'gender' => 'female',
                'date_of_birth' => '2000-02-02',
                'map_id' => 'MAP-3',
                'erp_number' => 'ERP-3',
            ],
        ]);

        $job = app(StudentImportService::class)->dryRun(['file' => $file]);
        $job->refresh();
        $this->assertSame('DryRunOK', $job->status);

        // Mark job as DryRunOK then commit synchronously
        $job->status = 'DryRunOK';
        $job->save();

        (new ProcessStudentImportJob($job))->handle();

        $job->refresh();
        $this->assertSame('Completed', $job->status);
        $this->assertSame(1, Student::count());
        $this->assertSame(1, User::where('kind', 'student')->count());

        $student = Student::first();
        $user = $student->user;

        $this->assertEquals('Taylor Swift', $student->full_name);
        $this->assertEquals('taylor@example.com', $student->email_address);
        $this->assertEquals('+919999888877', $student->mobile_number);
        $this->assertEquals('MAP-3', $student->map_id);
        $this->assertEquals('ERP-3', $student->erp_number);
        $this->assertEquals('Taylor Swift', $user->name);
    }

    public function test_google_form_export_headers_are_accepted_in_dry_run(): void
    {
        $headers = [
            'Timestamp',
            'Full Name',
            'Email Address',
            'Mobile Number',
            'Gender',
            'Date of Birth',
            'MAP ID',
            'Department',
            'Year of Study',
        ];

        $file = $this->makeXlsxWithHeaders($headers, [[
            'Timestamp' => '2026-02-25 11:00:00',
            'Full Name' => 'Jordan Miles',
            'Email Address' => 'jordan.miles@example.com',
            'Mobile Number' => '+919812345670',
            'Gender' => 'male',
            'Date of Birth' => '2004-09-15',
            'MAP ID' => 'MAP-9001',
            'Department' => 'Mechanical',
            'Year of Study' => '2',
        ]]);

        $job = app(StudentImportService::class)->dryRun(['file' => $file]);
        $job->refresh();

        $this->assertSame('DryRunOK', $job->status);
        $this->assertSame(1, $job->total_rows);
        $this->assertSame(0, $job->error_rows);
    }

    public function test_commit_handles_google_form_headers_end_to_end(): void
    {
        $headers = [
            'Timestamp',
            'Full Name',
            'Email Address',
            'Mobile Number',
            'Gender',
            'Date of Birth',
            'Student UID',
            'ERP Number',
            'Blood Group',
        ];

        $file = $this->makeXlsxWithHeaders($headers, [[
            'Timestamp' => '2026-02-25 12:00:00',
            'Full Name' => 'Avery Scott',
            'Email Address' => 'avery.scott@example.com',
            'Mobile Number' => '+919812345671',
            'Gender' => 'female',
            'Date of Birth' => '2003-06-10',
            'Student UID' => 'STD-GFORM-001',
            'ERP Number' => 'ERP-GFORM-001',
            'Blood Group' => 'B+',
        ]]);

        $job = app(StudentImportService::class)->dryRun(['file' => $file]);
        $job->refresh();

        $this->assertSame('DryRunOK', $job->status);

        (new ProcessStudentImportJob($job))->handle();

        $job->refresh();
        $this->assertSame('Completed', $job->status);

        $student = Student::firstOrFail();
        $this->assertSame('Avery Scott', $student->full_name);
        $this->assertSame('avery.scott@example.com', $student->email_address);
        $this->assertSame('+919812345671', $student->mobile_number);
        $this->assertSame('STD-GFORM-001', $student->student_uid);
        $this->assertSame('ERP-GFORM-001', $student->erp_number);
        $this->assertSame('B+', $student->blood_group);
    }

    /**
     * Build an XLSX UploadedFile with the configured headers and supplied rows.
     */
    private function makeXlsx(array $rows): UploadedFile
    {
        return $this->makeXlsxWithHeaders($this->headers, $rows);
    }

    /**
     * Build an XLSX UploadedFile with custom headers and supplied rows.
     */
    private function makeXlsxWithHeaders(array $headers, array $rows): UploadedFile
    {
        $relativePath = 'imports/test_students.xlsx';
        $absolutePath = Storage::disk('local')->path($relativePath);

        // Ensure directory exists
        Storage::disk('local')->makeDirectory('imports');

        $writer = SimpleExcelWriter::create($absolutePath)->addHeader($headers);

        foreach ($rows as $row) {
            $writer->addRow($row);
        }

        $writer->close();

        return new UploadedFile(
            $absolutePath,
            'students.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
