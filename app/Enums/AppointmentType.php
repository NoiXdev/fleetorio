<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AppointmentType: string implements HasColor, HasLabel
{
    case Service = 'service';
    case Workshop = 'workshop';
    case Inspection = 'inspection';
    case Accident = 'accident';
    case Damage = 'damage';

    public function getLabel(): string
    {
        return match ($this) {
            self::Service => 'Service',
            self::Workshop => 'Werkstatt',
            self::Inspection => 'Hauptuntersuchung',
            self::Accident => 'Unfall',
            self::Damage => 'Schaden',
        };
    }

    /**
     * 'danger' war bewusst gemieden, solange die Historie nur planbare
     * Termine kannte - das Panel verschiebt 'danger' auf Rose und 'primary'
     * ist das Markenrot (siehe AdminPanelProvider). Loeschen und Marke sollen
     * nicht mit gewoehnlichen Eintraegen verwechselt werden.
     *
     * Fuer den Unfall ist genau dieses Hervorstechen richtig: der Eintrag
     * soll ins Auge fallen, nicht gleich neben Service und Werkstatt
     * verschwinden.
     *
     * 'orange' fuer den Schaden musste neu registriert werden, weil 'warning'
     * bereits fuer die Hauptuntersuchung belegt ist und jede Farbe hier eine
     * einzelne Bedeutung tragen soll (siehe Farbtest).
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Service => 'info',
            self::Workshop => 'gray',
            self::Inspection => 'warning',
            self::Accident => 'danger',
            self::Damage => 'orange',
        };
    }
}
