<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FuelCardProvider: string implements HasLabel
{
    case Dkv = 'dkv';
    case Elli = 'elli';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dkv => 'DKV',
            self::Elli => 'Elli',
            self::Other => 'Sonstige',
        };
    }
}
