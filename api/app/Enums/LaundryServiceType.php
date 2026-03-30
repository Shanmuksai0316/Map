<?php

namespace App\Enums;

enum LaundryServiceType: string
{
    case WASH_ONLY = 'wash_only';
    case WASH_AND_IRON = 'wash_and_iron';
    case IRON_ONLY = 'iron_only';
    case DRY_CLEAN = 'dry_clean';
    case EXPRESS = 'express';

    public function getLabel(): string
    {
        return match($this) {
            self::WASH_ONLY => 'Wash Only',
            self::WASH_AND_IRON => 'Wash & Iron',
            self::IRON_ONLY => 'Iron Only',
            self::DRY_CLEAN => 'Dry Clean',
            self::EXPRESS => 'Express Service',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::WASH_ONLY => 'Regular washing service',
            self::WASH_AND_IRON => 'Washing and ironing service',
            self::IRON_ONLY => 'Ironing service only',
            self::DRY_CLEAN => 'Dry cleaning service',
            self::EXPRESS => 'Express washing service (same day)',
        };
    }

    public function getPriceMultiplier(): float
    {
        return match($this) {
            self::WASH_ONLY => 1.0,
            self::WASH_AND_IRON => 1.5,
            self::IRON_ONLY => 0.5,
            self::DRY_CLEAN => 2.0,
            self::EXPRESS => 1.8,
        };
    }

    public function getEstimatedDuration(): int // in hours
    {
        return match($this) {
            self::WASH_ONLY => 24,
            self::WASH_AND_IRON => 48,
            self::IRON_ONLY => 12,
            self::DRY_CLEAN => 72,
            self::EXPRESS => 8,
        };
    }

    public function requiresIroning(): bool
    {
        return in_array($this, [self::WASH_AND_IRON, self::IRON_ONLY]);
    }

    public function isExpress(): bool
    {
        return $this === self::EXPRESS;
    }

    public function isDryClean(): bool
    {
        return $this === self::DRY_CLEAN;
    }

    public static function getStandardServices(): array
    {
        return [self::WASH_ONLY, self::WASH_AND_IRON, self::IRON_ONLY];
    }

    public static function getPremiumServices(): array
    {
        return [self::DRY_CLEAN, self::EXPRESS];
    }

    public static function getAllServices(): array
    {
        return self::cases();
    }
}



