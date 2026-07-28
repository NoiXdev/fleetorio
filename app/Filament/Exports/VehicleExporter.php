<?php

namespace App\Filament\Exports;

use App\Models\Vehicle;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * Bewusst ohne Betraege: die Fahrzeugliste zeigt keine Kosten, also darf ihr
 * Export auch keine enthalten. Kosten werden ueber die Kostenseite exportiert,
 * die hinter dem Recht viewCosts liegt.
 */
class VehicleExporter extends Exporter
{
    protected static ?string $model = Vehicle::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('license_plate')->label('Kennzeichen'),
            ExportColumn::make('brand')->label('Marke'),
            ExportColumn::make('model')->label('Modell'),
            ExportColumn::make('fuel_type')->label('Antriebsart'),
            ExportColumn::make('employee.last_name')->label('Fahrer'),
            ExportColumn::make('fuelCard.provider')->label('Tankkarte'),
            ExportColumn::make('leasingCompany.name')->label('Leasinggeber'),
            ExportColumn::make('lease_start')->label('Leasingstart'),
            ExportColumn::make('lease_end')->label('Leasingende'),
            ExportColumn::make('status')->label('Status'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Der Fahrzeug-Export ist abgeschlossen.';
    }
}
