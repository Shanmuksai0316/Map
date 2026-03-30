<?php

namespace App\Enums;

enum LaundryRequestStatus: string
{
    case PENDING = 'pending';
    case SCHEDULED = 'scheduled';
    case COLLECTED = 'collected';
    case WASHING = 'washing';
    case DRYING = 'drying';
    case READY = 'ready';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case LOST = 'lost';
    case DAMAGED = 'damaged';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::SCHEDULED => 'Scheduled',
            self::COLLECTED => 'Collected',
            self::WASHING => 'Washing',
            self::DRYING => 'Drying',
            self::READY => 'Ready for Pickup',
            self::DELIVERED => 'Delivered',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::LOST => 'Lost',
            self::DAMAGED => 'Damaged',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::PENDING => 'Request submitted and waiting for processing',
            self::SCHEDULED => 'Request scheduled for collection',
            self::COLLECTED => 'Laundry collected from student',
            self::WASHING => 'Laundry is being washed',
            self::DRYING => 'Laundry is being dried',
            self::READY => 'Laundry is ready for pickup',
            self::DELIVERED => 'Laundry has been delivered',
            self::COMPLETED => 'Laundry service completed successfully',
            self::CANCELLED => 'Request was cancelled',
            self::LOST => 'Laundry item was lost',
            self::DAMAGED => 'Laundry item was damaged',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::PENDING => 'yellow',
            self::SCHEDULED => 'blue',
            self::COLLECTED => 'purple',
            self::WASHING => 'cyan',
            self::DRYING => 'indigo',
            self::READY => 'green',
            self::DELIVERED => 'emerald',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
            self::LOST => 'red',
            self::DAMAGED => 'orange',
        };
    }

    public function isActive(): bool
    {
        return !in_array($this, [self::COMPLETED, self::CANCELLED, self::LOST, self::DAMAGED]);
    }

    public function isInProgress(): bool
    {
        return in_array($this, [self::COLLECTED, self::WASHING, self::DRYING]);
    }

    public function isCompleted(): bool
    {
        return in_array($this, [self::COMPLETED, self::DELIVERED]);
    }

    public function isFailed(): bool
    {
        return in_array($this, [self::CANCELLED, self::LOST, self::DAMAGED]);
    }

    public function canTransitionTo(LaundryRequestStatus $newStatus): bool
    {
        return match($this) {
            self::PENDING => in_array($newStatus, [self::SCHEDULED, self::CANCELLED]),
            self::SCHEDULED => in_array($newStatus, [self::COLLECTED, self::CANCELLED]),
            self::COLLECTED => in_array($newStatus, [self::WASHING, self::CANCELLED, self::LOST, self::DAMAGED]),
            self::WASHING => in_array($newStatus, [self::DRYING, self::CANCELLED, self::LOST, self::DAMAGED]),
            self::DRYING => in_array($newStatus, [self::READY, self::CANCELLED, self::LOST, self::DAMAGED]),
            self::READY => in_array($newStatus, [self::DELIVERED, self::CANCELLED, self::LOST, self::DAMAGED]),
            self::DELIVERED => in_array($newStatus, [self::COMPLETED]),
            self::COMPLETED => false, // Terminal state
            self::CANCELLED => false, // Terminal state
            self::LOST => false, // Terminal state
            self::DAMAGED => false, // Terminal state
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
