<?php

use App\Domain\RoomChanges\Models\RoomChange;
use App\Jobs\SendRoomChangeEscalationNotification;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

it('dispatches escalation jobs for overdue room changes', function (): void {
    Queue::fake();

    $tenant = Tenant::factory()->create();
    Role::findOrCreate('Campus Manager');

    $manager = User::factory()->create([
        'tenant_id' => $tenant->id,
        'phone' => '+911111111111',
    ]);
    $manager->assignRole('Campus Manager');

    $student = Student::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $roomChange = RoomChange::factory()->create([
        'tenant_id' => $tenant->id,
        'student_id' => $student->id,
        'status' => 'pending',
        'submitted_at' => Carbon::now()->subDay(),
        'sla_due_at' => Carbon::now()->subHours(2),
    ]);

    artisan('room-changes:escalate')->assertExitCode(0);

    Queue::assertPushed(SendRoomChangeEscalationNotification::class, function ($job) use ($roomChange): bool {
        return (int) $job->roomChangeId === $roomChange->id;
    });

    expect($roomChange->fresh()->last_escalated_at)->not()->toBeNull();
});

