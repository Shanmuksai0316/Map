<?php

namespace App\Enums;

enum OutPassType: string
{
    case NORMAL = 'normal';
    case LEAVE = 'leave';
    case SICK = 'sick';

    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal Outing',
            self::LEAVE => 'Leave',
            self::SICK => 'Medical',
        };
    }
}
