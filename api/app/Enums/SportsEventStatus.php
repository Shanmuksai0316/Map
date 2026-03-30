<?php

namespace App\Enums;

enum SportsEventStatus: string
{
    case SCHEDULED = 'scheduled';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [
            self::SCHEDULED,
            self::ONGOING,
        ]);
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::SCHEDULED => in_array($newStatus, [self::ONGOING, self::COMPLETED, self::CANCELLED]),
            self::ONGOING => in_array($newStatus, [self::COMPLETED, self::CANCELLED]),
            default => false, // Completed and Cancelled are terminal states
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Scheduled',
            self::ONGOING => 'Ongoing',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SCHEDULED => 'blue',
            self::ONGOING => 'green',
            self::COMPLETED => 'gray',
            self::CANCELLED => 'red',
        };
    }
}



