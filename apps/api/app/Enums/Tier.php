<?php

declare(strict_types=1);

namespace App\Enums;

enum Tier: string
{
    case Budget = 'budget';
    case Mid = 'mid';
    case Premium = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::Budget => 'Voordelig',
            self::Mid => 'Middenklasse',
            self::Premium => 'Premium',
        };
    }
}
