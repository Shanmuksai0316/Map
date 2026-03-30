<?php

namespace App\Enums;

enum OutPassStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case DECLINED = 'declined';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::DECLINED,
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::DECLINED,
            self::REJECTED => 'danger',
            self::CANCELLED => 'gray',
            self::EXPIRED => 'gray',
        };
    }
}
