<?php

namespace App\Filament\Exports;

use App\Models\Vehicle;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class VehicleCostExporter extends Exporter
{
    protected static ?string $model = Vehicle::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('license_plate')->label('Kennzeichen'),
            ExportColumn::make('brand')->label('Marke'),
            ExportColumn::make('model')->label('Modell'),
            ExportColumn::make('monthly_lease_rate')->label('Leasingrate monatlich'),
            ExportColumn::make('annual_vehicle_tax')->label('Kfz-Steuer jaehrlich'),
            ExportColumn::make('annual_insurance')->label('Versicherung jaehrlich'),
            ExportColumn::make('annual_total_cost')
                ->label('Jahreskosten')
                // annual_total_cost ist ein reiner float-Accessor (siehe
                // Vehicle::annualTotalCost()), keine decimal:2-Spalte. Ohne
                // explizite Formatierung schreibt Filament ihn per
                // String-Wandlung in die CSV, was glatte Betraege ohne
                // Nachkommastellen erzeugt (z.B. "6200" statt "6200.00") -
                // inkonsistent mit den drei decimal:2-Spalten daneben.
                ->formatStateUsing(fn (float $state): string => number_format($state, 2, '.', '')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Der Kosten-Export ist abgeschlossen.';
    }

    // Deutsches Excel interpretiert Komma als Dezimaltrennzeichen und zerlegt
    // eine kommagetrennte CSV daher nicht in Spalten - fuer die Buchhaltung,
    // die diesen Export weiterverarbeitet, waere die Datei sonst unbrauchbar.
    public static function getCsvDelimiter(): string
    {
        return ';';
    }
}
