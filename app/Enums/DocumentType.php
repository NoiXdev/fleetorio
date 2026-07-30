<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentType: string implements HasColor, HasLabel
{
    case Registration = 'registration';
    case LeaseContract = 'lease_contract';
    case Handover = 'handover';
    case InspectionReport = 'inspection_report';
    case Insurance = 'insurance';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Registration => 'Fahrzeugschein',
            self::LeaseContract => 'Leasingvertrag',
            self::Handover => 'Übergabeprotokoll',
            self::InspectionReport => 'HU-Bericht',
            self::Insurance => 'Versicherungsschein',
            self::Other => 'Sonstiges',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Registration => 'info',
            self::LeaseContract => 'warning',
            self::Handover => 'gray',
            self::InspectionReport => 'success',
            self::Insurance => 'primary',
            self::Other => 'gray',
        };
    }
}
