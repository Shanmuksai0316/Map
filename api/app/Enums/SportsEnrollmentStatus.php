<?php

namespace App\Enums;

enum SportsEnrollmentStatus: string
{
    case REGISTERED = 'registered';
    case WAITLISTED = 'waitlisted';
    case ATTENDED = 'attended';
    case NO_SHOW = 'no_show';
    case CANCELLED = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [
            self::REGISTERED,
            self::WAITLISTED,
        ]);
    }

    public function isCompleted(): bool
    {
        return in_array($this, [
            self::ATTENDED,
            self::NO_SHOW,
        ]);
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::REGISTERED => in_array($newStatus, [self::ATTENDED, self::NO_SHOW, self::CANCELLED, self::WAITLISTED]),
            self::WAITLISTED => in_array($newStatus, [self::REGISTERED, self::ATTENDED, self::NO_SHOW, self::CANCELLED]),
            self::ATTENDED, self::NO_SHOW, self::CANCELLED => false, // Terminal states
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::REGISTERED => 'Registered',
            self::WAITLISTED => 'Waitlisted',
            self::ATTENDED => 'Attended',
            self::NO_SHOW => 'No Show',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::REGISTERED => 'blue',
            self::WAITLISTED => 'yellow',
            self::ATTENDED => 'green',
            self::NO_SHOW => 'red',
            self::CANCELLED => 'gray',
        };
    }
}



