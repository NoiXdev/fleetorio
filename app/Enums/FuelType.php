<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FuelType: string implements HasLabel
{
    case Petrol = 'petrol';
    case Diesel = 'diesel';
    case Hybrid = 'hybrid';
    case PluginHybrid = 'plugin_hybrid';
    case Electric = 'electric';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Petrol => 'Benzin',
            self::Diesel => 'Diesel',
            self::Hybrid => 'Hybrid',
            self::PluginHybrid => 'Plug-in-Hybrid',
            self::Electric => 'Elektro',
            self::Other => 'Sonstige',
        };
    }
}
