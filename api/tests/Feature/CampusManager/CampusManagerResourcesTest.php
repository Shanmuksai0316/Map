<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Filament\CampusManager\Resources\AttendanceSessionResource;
use App\Filament\CampusManager\Resources\AttendanceSessionResource\Pages\ListAttendanceSessions;
use App\Filament\CampusManager\Resources\OutPassResource;
use App\Filament\CampusManager\Resources\OutPassResource\Pages\ListOutPasses;
use App\Filament\CampusManager\Resources\StudentResource;
use App\Filament\CampusManager\Resources\StudentResource\Pages\ListStudents;
use App\Models\Campus;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeTestForm(?string $modelClass = null): \Filament\Forms\Form
{
    $form = \Filament\Forms\Form::make(new class implements \Filament\Forms\Contracts\HasForms {
        use \Filament\Forms\Concerns\InteractsWithForms;
    });

    if ($modelClass) {
        $form->model($modelClass);
    }

    return $form;
}

/**
 * Boot the Campus Manager panel for tests and create a scoped user.
 *
 * @return array{tenant: \App\Models\Tenant, campus: \App\Models\Campus, hostel: \App\Models\Hostel, manager: \App\Models\User}
 */
function createCampusManagerContext(array $tenantOverrides = []): array
{
    $tenant = Tenant::factory()->create(array_merge([
        'status' => TenantStatus::ACTIVE,
        'addon_security' => true,
        'addon_sports' => true,
        'addon_laundry' => true,
    ], $tenantOverrides));

    // Ensure global helpers can resolve a default tenant id
    app()->instance('testing.default_tenant_id', $tenant->id);

    $campus = Campus::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $hostel = Hostel::factory()->create([
        'tenant_id' => $tenant->id,
        'campus_id' => $campus->id,
    ]);

    $manager = User::factory()->campusManager()->create([
        'tenant_id' => $tenant->id,
        'email' => 'cm-'.$tenant->id.'@example.com',
    ]);

    // Ensure the Campus Manager role exists on both guards
    Role::findOrCreate('Campus Manager', 'web');
    Role::findOrCreate('Campus Manager', 'sanctum');

    $manager->assignRole('Campus Manager');

    Livewire::actingAs($manager);

    // Filament must know which panel is active
    $panel = Filament::getPanel('campus-manager');
    Filament::setCurrentPanel($panel);
    Filament::setTenant($tenant, true);

    return compact('tenant', 'campus', 'hostel', 'manager');
}

it('scopes student listings to the campus managers tenant', function (): void {
    $context = createCampusManagerContext();

    /** @var User $tenantStudentUser */
    $tenantStudentUser = User::factory()->student()->create([
        'tenant_id' => $context['tenant']->id,
        'email' => 'student-'.$context['tenant']->id.'@example.com',
    ]);

    $tenantStudent = $tenantStudentUser->student;
    $tenantStudent->update([
        'tenant_id' => $context['tenant']->id,
        'hostel_id' => $context['hostel']->id,
    ]);

    $otherTenant = Tenant::factory()->create();

    /** @var User $otherStudentUser */
    $otherStudentUser = User::factory()->student()->create([
        'tenant_id' => $otherTenant->id,
        'email' => 'other-'.$otherTenant->id.'@example.com',
    ]);

    $otherStudent = $otherStudentUser->student;
    $otherStudent->update([
        'tenant_id' => $otherTenant->id,
        'hostel_id' => null,
    ]);

    $component = Livewire::test(ListStudents::class, ['tenant' => $context['tenant']]);
    $visibleIds = $component->instance()->getTableQuery()->pluck('id')->all();

    expect($visibleIds)
        ->toContain($tenantStudent->id)
        ->not->toContain($otherStudent->id);
});

it('filters outpasses by tenant and exposes bulk actions', function (): void {
    $context = createCampusManagerContext();

    /** @var User $studentUser */
    $studentUser = User::factory()->student()->create([
        'tenant_id' => $context['tenant']->id,
        'email' => 'student-outpass-'.$context['tenant']->id.'@example.com',
    ]);

    $student = $studentUser->student;
    $student->update([
        'tenant_id' => $context['tenant']->id,
        'hostel_id' => $context['hostel']->id,
    ]);

    $tenantOutPass = OutPass::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'student_id' => $student->id,
        'hostel_id' => $context['hostel']->id,
    ]);

    $otherTenant = Tenant::factory()->create();
    $otherHostel = Hostel::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    $otherStudentUser = User::factory()->student()->create([
        'tenant_id' => $otherTenant->id,
        'email' => 'student-other-'.$otherTenant->id.'@example.com',
    ]);

    $otherStudent = $otherStudentUser->student;
    $otherStudent->update([
        'tenant_id' => $otherTenant->id,
        'hostel_id' => $otherHostel->id,
    ]);

    OutPass::factory()->create([
        'tenant_id' => $otherTenant->id,
        'student_id' => $otherStudent->id,
        'hostel_id' => $otherHostel->id,
    ]);

    $component = Livewire::test(ListOutPasses::class, ['tenant' => $context['tenant']]);

    $visibleIds = $component->instance()->getTableQuery()->pluck('id')->all();

    expect($visibleIds)
        ->toContain($tenantOutPass->id)
        ->toHaveCount(1);

    $table = $component->instance()->getTable();
    $actions = $table->getBulkActions();

    expect($actions)->not->toBeEmpty();
    expect(collect($actions)->pluck('name'))->toContain('approve');
});

it('hides attendance resource when module disabled and shows when enabled', function (): void {
    $original = Config::get('features.attendance_module', true);

    try {
        Config::set('features.attendance_module', false);
        $context = createCampusManagerContext();

        expect(AttendanceSessionResource::canViewAny())->toBeFalse();

        Config::set('features.attendance_module', true);
        expect(AttendanceSessionResource::canViewAny())->toBeTrue();

        // Create an attendance session and ensure it surfaces in the table
        $session = AttendanceSession::factory()->create([
            'tenant_id' => $context['tenant']->id,
            'hostel_id' => $context['hostel']->id,
            'session_date' => Carbon::today(),
            'session_time' => Carbon::now()->format('H:i:s'),
            'status' => 'in_progress',
        ]);

        $component = Livewire::test(ListAttendanceSessions::class, ['tenant' => $context['tenant']]);
        $ids = $component->instance()->getTableQuery()->pluck('id')->all();

        expect($ids)->toContain($session->id);
    } finally {
        Config::set('features.attendance_module', $original);
    }
});

it('disables structural hostel and room changes once tenant is active', function (): void {
    $context = createCampusManagerContext([
        'status' => TenantStatus::ACTIVE,
    ]);

    $hostelPage = \App\Filament\CampusManager\Resources\HostelResource::form(
        makeTestForm(\App\Models\Hostel::class)
    );

    $roomPage = \App\Filament\CampusManager\Resources\RoomResource::form(
        makeTestForm(\App\Models\Room::class)
    );

    $hostelInputs = collect($hostelPage->getFlatComponents())->filter(fn ($component) => method_exists($component, 'isDisabled'));
    $roomInputs = collect($roomPage->getFlatComponents())->filter(fn ($component) => method_exists($component, 'isDisabled'));

    $hostelCode = $hostelInputs->first(fn ($component) => $component->getName() === 'code');
    $roomCapacity = $roomInputs->first(fn ($component) => $component->getName() === 'capacity');

    expect($hostelCode->isDisabled())->toBeTrue();
    expect($roomCapacity->isDisabled())->toBeTrue();
}
);


