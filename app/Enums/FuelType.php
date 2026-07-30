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
     * Jede Antriebsart bekommt ein eigenes Symbol. Zwei Arten mit demselben
     * Symbol wären schlimmer als keins: es steht neben dem Text und soll ihn
     * auf einen Blick unterscheidbar machen, nicht bloß schmücken.
     *
     * Heroicons v2 hat kein Zapfsäulen- und kein Tropfen-Symbol (nur die
     * Pipette, die an ein Werkzeug erinnert), daher die Umschreibungen:
     * Flamme für den Ottomotor, Becher für den Kraftstoff des Diesels, zwei
     * die Richtung tauschende Pfeile für den Wechsel zweier Antriebsquellen
     * beim Hybrid, Stecker für die Ladbarkeit des Plug-in-Hybrids, Blitz für
     * rein elektrisch. Sonstige bleibt neutral (Fragezeichen), weil die
     * Antriebsart dort gerade nicht feststeht.
     */
    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::Petrol => Heroicon::OutlinedFire,
            self::Diesel => Heroicon::OutlinedBeaker,
            self::Hybrid => Heroicon::OutlinedArrowsRightLeft,
            self::PluginHybrid => Heroicon::OutlinedPower,
            self::Electric => Heroicon::OutlinedBolt,
            self::Other => Heroicon::OutlinedQuestionMarkCircle,
        };
    }
}
