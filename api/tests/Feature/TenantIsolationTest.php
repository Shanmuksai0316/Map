<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $tenantAUser;
    private User $tenantBUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['code' => 'TENANT-A']);
        $this->tenantB = Tenant::factory()->create(['code' => 'TENANT-B']);

        $this->tenantAUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'kind' => 'staff',
        ]);
        $this->tenantAUser->assignRole('Campus Manager');

        $this->tenantBUser = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'kind' => 'staff',
        ]);
        $this->tenantBUser->assignRole('Campus Manager');
    }

    /** @test */
    public function students_are_scoped_to_the_authenticated_tenant(): void
    {
        $tenantAStudents = Student::factory()->count(2)->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        Student::factory()->count(2)->create([
            'tenant_id' => $this->tenantB->id,
        ]);

        $this->actingAs($this->tenantAUser);

        $visibleStudents = Student::all();
        $this->assertCount($tenantAStudents->count(), $visibleStudents);
        $this->assertTrue(
            $visibleStudents->every(fn ($student) => $student->tenant_id === $this->tenantA->id)
        );
    }

    /** @test */
    public function tenant_user_cannot_fetch_other_tenant_student_by_id(): void
    {
        $studentB = Student::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);

        $this->actingAs($this->tenantAUser);

        $fetched = Student::find($studentB->id);
        $this->assertNull($fetched, 'Tenant scope should block cross-tenant student access');
    }

    /** @test */
    public function tenant_user_cannot_mutate_other_tenant_student(): void
    {
        $studentB = Student::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'full_name' => 'Tenant B Student',
        ]);

        $this->actingAs($this->tenantAUser);

        $updated = Student::where('id', $studentB->id)->update(['full_name' => 'Hacked']);
        $this->assertEquals(0, $updated, 'Update should be blocked by tenant scope');

        $studentB->refresh();
        $this->assertEquals('Tenant B Student', $studentB->full_name);
    }
}
