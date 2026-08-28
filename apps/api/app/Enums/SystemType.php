<?php

declare(strict_types=1);

namespace App\Enums;

enum SystemType: string
{
    case SingleSplit = 'single_split';
    case MultiSplit = 'multi_split';

    public function label(): string
    {
        return match ($this) {
            self::SingleSplit => 'Single split',
            self::MultiSplit => 'Multisplit',
        };
    }
}
