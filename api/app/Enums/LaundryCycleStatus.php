<?php

namespace App\Enums;

enum LaundryCycleStatus: string
{
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case WASHING = 'washing';
    case DRYING = 'drying';
    case READY = 'ready';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match($this) {
            self::SCHEDULED => 'Scheduled',
            self::IN_PROGRESS => 'In Progress',
            self::WASHING => 'Washing',
            self::DRYING => 'Drying',
            self::READY => 'Ready',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::SCHEDULED => 'Cycle is scheduled to start',
            self::IN_PROGRESS => 'Cycle is currently running',
            self::WASHING => 'Washing phase in progress',
            self::DRYING => 'Drying phase in progress',
            self::READY => 'Cycle completed, items ready for delivery',
            self::COMPLETED => 'Cycle completed successfully',
            self::CANCELLED => 'Cycle was cancelled',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::SCHEDULED => 'blue',
            self::IN_PROGRESS => 'yellow',
            self::WASHING => 'cyan',
            self::DRYING => 'indigo',
            self::READY => 'green',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
        };
    }

    public function isActive(): bool
    {
        return !in_array($this, [self::COMPLETED, self::CANCELLED]);
    }

    public function isInProgress(): bool
    {
        return in_array($this, [self::IN_PROGRESS, self::WASHING, self::DRYING]);
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this === self::CANCELLED;
    }

    public function canTransitionTo(LaundryCycleStatus $newStatus): bool
    {
        return match($this) {
            self::SCHEDULED => in_array($newStatus, [self::IN_PROGRESS, self::CANCELLED]),
            self::IN_PROGRESS => in_array($newStatus, [self::WASHING, self::CANCELLED]),
            self::WASHING => in_array($newStatus, [self::DRYING, self::CANCELLED]),
            self::DRYING => in_array($newStatus, [self::READY, self::CANCELLED]),
            self::READY => in_array($newStatus, [self::COMPLETED, self::CANCELLED]),
            self::COMPLETED => false, // Terminal state
            self::CANCELLED => false, // Terminal state
        };
    }

    public static function getActiveStatuses(): array
    {
        return array_filter(
            self::cases(),
            fn($status) => $status->isActive()
        );
    }

    public static function getInProgressStatuses(): array
    {
        return array_filter(
            self::cases(),
            fn($status) => $status->isInProgress()
        );
    }

    public static function getCompletedStatuses(): array
    {
        return array_filter(
            self::cases(),
            fn($status) => $status->isCompleted()
        );
    }

    public static function getFailedStatuses(): array
    {
        return array_filter(
            self::cases(),
            fn($status) => $status->isFailed()
        );
    }
}



