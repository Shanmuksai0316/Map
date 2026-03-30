<?php

namespace App\Filament\CampusManager\Widgets\CampusManager;

use App\Services\Checklists\ChecklistReminderService;
use App\Services\RoomChanges\RoomChangeReminderService;
use Filament\Widgets\Widget;

class ReminderWidget extends Widget
{
    protected static string $view = 'filament.campus-manager.widgets.reminders';

    protected int|string|array $columnSpan = [
        'md' => 1,
    ];

    protected function getViewData(): array
    {
        $roomService = app(RoomChangeReminderService::class);
        $checklistService = app(ChecklistReminderService::class);

        $roomSummary = $roomService?->summary() ?? [
            'pending' => 0,
            'overdue' => 0,
            'nextDueAt' => null,
        ];

        $checklistSummary = $checklistService?->summary() ?? [
            'pendingToday' => 0,
            'morningPending' => 0,
            'afternoonPending' => 0,
            'overdue' => 0,
        ];

        return [
            'roomSummary' => $roomSummary,
            'checklistSummary' => $checklistSummary,
        ];
    }
}

