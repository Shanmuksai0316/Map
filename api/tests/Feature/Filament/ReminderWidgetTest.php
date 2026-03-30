<?php

use App\Filament\CampusManager\Widgets\CampusManager\ReminderWidget;
use App\Services\Checklists\ChecklistReminderService;
use App\Services\RoomChanges\RoomChangeReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Mockery;

uses(RefreshDatabase::class);

it('renders reminder summary data', function (): void {
    $roomService = Mockery::mock(RoomChangeReminderService::class);
    $roomService->shouldReceive('summary')->once()->andReturn([
        'pending' => 5,
        'overdue' => 2,
        'nextDueAt' => now()->addHour(),
    ]);

    $checklistService = Mockery::mock(ChecklistReminderService::class);
    $checklistService->shouldReceive('summary')->once()->andReturn([
        'pendingToday' => 12,
        'morningPending' => 4,
        'afternoonPending' => 3,
        'overdue' => 1,
    ]);

    App::instance(RoomChangeReminderService::class, $roomService);
    App::instance(ChecklistReminderService::class, $checklistService);

    $widget = new ReminderWidget();
    $data = $widget->getViewData();

    expect($data['roomSummary']['pending'])->toBe(5)
        ->and($data['checklistSummary']['overdue'])->toBe(1);
});

