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
            ExportColumn::make('annual_total_cost')->label('Jahreskosten'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Der Kosten-Export ist abgeschlossen.';
    }
}
