<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AppointmentType: string implements HasColor, HasLabel
{
    case Service = 'service';
    case Workshop = 'workshop';
    case Inspection = 'inspection';

    public function getLabel(): string
    {
        return match ($this) {
            self::Service => 'Service',
            self::Workshop => 'Werkstatt',
            self::Inspection => 'Hauptuntersuchung',
        };
    }

    /**
     * 'danger' ist in diesem Panel auf Rose verschoben und 'primary' ist das
     * Markenrot (siehe AdminPanelProvider) - beide daher hier bewusst
     * gemieden, damit Termin-Badges nicht mit Loeschen/Marke verwechselt
     * werden.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Service => 'info',
            self::Workshop => 'gray',
            self::Inspection => 'warning',
        };
    }
}
