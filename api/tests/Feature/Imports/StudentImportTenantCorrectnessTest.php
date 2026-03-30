<?php

namespace Tests\Feature\Imports;

use App\Events\StudentActivated;
use App\Jobs\ProcessStudentImportJob;
use App\Models\ImportJob;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentImportTenantCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_assigns_students_to_correct_tenant(): void
    {
        Event::fake();
        
        $tenant1 = Tenant::factory()->create(['name' => 'University A']);
        $tenant2 = Tenant::factory()->create(['name' => 'University B']);
        
        // Create CSV content
        $csvContent = "student_uid,name,phone,map_student_id\n";
        $csvContent .= "STU001,John Doe,+919876543210,MAP001\n";
        $csvContent .= "STU002,Jane Smith,+919876543211,MAP002\n";
        
        // Store CSV file
        $filename = 'test-import.csv';
        Storage::put($filename, $csvContent);
        
        // Create import job for tenant1
        $importJob = ImportJob::factory()->create([
            'tenant_id' => $tenant1->id,
            'kind' => 'students',
            'filename' => $filename,
            'status' => 'Queued'
        ]);
        
        // Process the import
        $job = new ProcessStudentImportJob($importJob);
        $job->handle();
        
        // Verify students were created for tenant1 only
        $this->assertEquals(2, Student::where('tenant_id', $tenant1->id)->count());
        $this->assertEquals(0, Student::where('tenant_id', $tenant2->id)->count());
        
        // Verify student details
        $student1 = Student::where('tenant_id', $tenant1->id)
            ->where('student_uid', 'STU001')
            ->first();
        
        $this->assertNotNull($student1);
        $this->assertEquals('MAP001', $student1->map_student_id);
        $this->assertEquals('John Doe', $student1->user->name);
        $this->assertEquals('+919876543210', $student1->user->phone);
        
        // Verify events were fired
        Event::assertDispatched(StudentActivated::class, 2);
        
        // Clean up
        Storage::delete($filename);
    }

    public function test_import_is_idempotent_when_run_twice(): void
    {
        Event::fake();
        
        $tenant = Tenant::factory()->create();
        
        // Create CSV content
        $csvContent = "student_uid,name,phone,map_student_id\n";
        $csvContent .= "STU001,John Doe,+919876543210,MAP001\n";
        $csvContent .= "STU002,Jane Smith,+919876543211,MAP002\n";
        
        // Store CSV file
        $filename = 'test-import-idempotent.csv';
        Storage::put($filename, $csvContent);
        
        // Create import job
        $importJob = ImportJob::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'students',
            'filename' => $filename,
            'status' => 'Queued'
        ]);
        
        // Process the import first time
        $job = new ProcessStudentImportJob($importJob);
        $job->handle();
        
        $firstRunCount = Student::where('tenant_id', $tenant->id)->count();
        $firstRunEvents = Event::dispatched(StudentActivated::class)->count();
        
        // Reset event tracking
        Event::fake();
        
        // Process the import second time
        $job2 = new ProcessStudentImportJob($importJob);
        $job2->handle();
        
        $secondRunCount = Student::where('tenant_id', $tenant->id)->count();
        $secondRunEvents = Event::dispatched(StudentActivated::class)->count();
        
        // Should have same count (no duplicates)
        $this->assertEquals($firstRunCount, $secondRunCount);
        $this->assertEquals(2, $firstRunCount); // Original count
        
        // Should not fire activation events on second run (no new students)
        $this->assertEquals(0, $secondRunEvents);
        
        // Clean up
        Storage::delete($filename);
    }

    public function test_import_handles_duplicate_map_student_id_correctly(): void
    {
        Event::fake();
        
        $tenant = Tenant::factory()->create();
        
        // Create existing student with MAP001
        $existingStudent = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'student_uid' => 'OLD001',
            'map_student_id' => 'MAP001'
        ]);
        
        // Create CSV with same map_student_id but different UID
        $csvContent = "student_uid,name,phone,map_student_id\n";
        $csvContent .= "NEW001,John Doe,+919876543210,MAP001\n";
        
        // Store CSV file
        $filename = 'test-import-duplicate.csv';
        Storage::put($filename, $csvContent);
        
        // Create import job
        $importJob = ImportJob::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'students',
            'filename' => $filename,
            'status' => 'Queued'
        ]);
        
        // Process the import
        $job = new ProcessStudentImportJob($importJob);
        $job->handle();
        
        // Should update existing student, not create new one
        $this->assertEquals(1, Student::where('tenant_id', $tenant->id)->count());
        
        $updatedStudent = Student::find($existingStudent->id);
        $this->assertEquals('NEW001', $updatedStudent->student_uid);
        $this->assertEquals('MAP001', $updatedStudent->map_student_id);
        $this->assertEquals('John Doe', $updatedStudent->user->name);
        
        // Should not fire activation event (update, not new creation)
        Event::assertNotDispatched(StudentActivated::class);
        
        // Clean up
        Storage::delete($filename);
    }

    public function test_import_respects_unique_constraint_on_tenant_map_student_id(): void
    {
        $tenant = Tenant::factory()->create();
        
        // Create CSV with duplicate map_student_id within same tenant
        $csvContent = "student_uid,name,phone,map_student_id\n";
        $csvContent .= "STU001,John Doe,+919876543210,MAP001\n";
        $csvContent .= "STU002,Jane Smith,+919876543211,MAP001\n"; // Same map_student_id
        
        // Store CSV file
        $filename = 'test-import-unique-constraint.csv';
        Storage::put($filename, $csvContent);
        
        // Create import job
        $importJob = ImportJob::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'students',
            'filename' => $filename,
            'status' => 'Queued'
        ]);
        
        // This should not throw an exception due to unique constraint
        // The second student should update the first one
        $job = new ProcessStudentImportJob($importJob);
        $job->handle();
        
        // Should have only one student (second one updated the first)
        $this->assertEquals(1, Student::where('tenant_id', $tenant->id)->count());
        
        $student = Student::where('tenant_id', $tenant->id)->first();
        $this->assertEquals('STU002', $student->student_uid); // Last one wins
        $this->assertEquals('Jane Smith', $student->user->name);
        
        // Clean up
        Storage::delete($filename);
    }

    public function test_import_creates_users_with_correct_tenant_id(): void
    {
        $tenant = Tenant::factory()->create();
        
        // Create CSV content
        $csvContent = "student_uid,name,phone,map_student_id\n";
        $csvContent .= "STU001,John Doe,+919876543210,MAP001\n";
        
        // Store CSV file
        $filename = 'test-import-user-tenant.csv';
        Storage::put($filename, $csvContent);
        
        // Create import job
        $importJob = ImportJob::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'students',
            'filename' => $filename,
            'status' => 'Queued'
        ]);
        
        // Process the import
        $job = new ProcessStudentImportJob($importJob);
        $job->handle();
        
        // Verify user was created with correct tenant_id
        $user = User::where('tenant_id', $tenant->id)
            ->where('name', 'John Doe')
            ->where('kind', 'student')
            ->first();
        
        $this->assertNotNull($user);
        $this->assertEquals($tenant->id, $user->tenant_id);
        $this->assertEquals('student', $user->kind);
        
        // Clean up
        Storage::delete($filename);
    }

    public function test_import_tracks_activation_events_correctly(): void
    {
        Event::fake();
        
        $tenant = Tenant::factory()->create();
        
        // Create existing student
        $existingStudent = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'student_uid' => 'EXISTING',
            'map_student_id' => 'EXISTING_MAP'
        ]);
        
        // Create CSV with one new student and one existing
        $csvContent = "student_uid,name,phone,map_student_id\n";
        $csvContent .= "EXISTING,Updated Name,+919876543210,EXISTING_MAP\n"; // Existing
        $csvContent .= "NEW001,New Student,+919876543211,NEW_MAP\n"; // New
        
        // Store CSV file
        $filename = 'test-import-events.csv';
        Storage::put($filename, $csvContent);
        
        // Create import job
        $importJob = ImportJob::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'students',
            'filename' => $filename,
            'status' => 'Queued'
        ]);
        
        // Process the import
        $job = new ProcessStudentImportJob($importJob);
        $job->handle();
        
        // Should fire activation event only for new student
        Event::assertDispatched(StudentActivated::class, 1);
        
        // Verify the event was for the new student
        Event::assertDispatched(StudentActivated::class, function (StudentActivated $event) {
            return $event->student->student_uid === 'NEW001';
        });
        
        // Clean up
        Storage::delete($filename);
    }
}
