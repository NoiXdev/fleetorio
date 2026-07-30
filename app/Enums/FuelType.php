<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum FuelType: string implements HasIcon, HasLabel
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

    /**
     * Heroicons v2 kennt kein reines "Tropfen"-Symbol (nur die Pipette
     * o-eye-dropper, die eher an ein Werkzeug erinnert) - deshalb Feuer fuer
     * beide Verbrenner-Antriebsarten, sachlich passend zur Verbrennung.
     * Blitz fuer Elektro ist die naheliegende Wahl. Fuer Hybrid und
     * Plug-in-Hybrid steht "zwei Pfeile, die die Richtung tauschen"
     * (arrows-right-left) fuer das Wechseln zwischen zwei Antriebsquellen -
     * das trifft den Kern eines Hybridantriebs besser als ein beliebiges
     * "gemischtes" Symbol ohne inhaltlichen Bezug. Sonstige bleibt bewusst
     * neutral (Fragezeichen), weil die Antriebsart hier nicht feststeht.
     */
    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::Petrol, self::Diesel => Heroicon::OutlinedFire,
            self::Hybrid, self::PluginHybrid => Heroicon::OutlinedArrowsRightLeft,
            self::Electric => Heroicon::OutlinedBolt,
            self::Other => Heroicon::OutlinedQuestionMarkCircle,
        };
    }
}
