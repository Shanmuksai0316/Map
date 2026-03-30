<?php

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Jobs\SendChecklistReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

it('dispatches morning checklist reminders via command', function (): void {
    Queue::fake();

    $instance = ChecklistInstance::factory()->create([
        'date' => Carbon::today()->toDateString(),
        'status' => ChecklistInstance::STATUS_PENDING,
        'morning_reminded_at' => null,
    ]);

    artisan('checklists:remind --window=morning')->assertExitCode(0);

    Queue::assertPushed(SendChecklistReminderNotification::class, function ($job) use ($instance): bool {
        return $job->checklistInstanceId === $instance->id && $job->window === 'morning';
    });

    expect($instance->fresh()->morning_reminded_at)->not()->toBeNull();
});

