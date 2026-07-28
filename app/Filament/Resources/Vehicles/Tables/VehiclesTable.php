<?php

namespace App\Filament\Resources\Vehicles\Tables;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use App\Filament\Exports\VehicleExporter;
use App\Models\Vehicle;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('license_plate')
                    ->label('Kennzeichen')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand')
                    ->label('Marke')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('model')
                    ->label('Modell')
                    ->searchable(),
                TextColumn::make('employee.last_name')
                    ->label('Fahrer')
                    ->formatStateUsing(fn ($state, Vehicle $record): string => $record->employee?->full_name ?? '—')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('fuel_type')
                    ->label('Antriebsart')
                    ->badge()
                    ->sortable(),
                TextColumn::make('lease_end')
                    ->label('Leasingende')
                    ->date('d.m.Y')
                    ->badge()
                    ->color(function (Vehicle $record): string {
                        $days = today()->diffInDays($record->lease_end, false);

                        return match (true) {
                            $days < 0 => 'gray',
                            $days <= 90 => 'danger',
                            $days <= Vehicle::LEASE_WARNING_DAYS => 'warning',
                            default => 'success',
                        };
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('lease_end')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(VehicleStatus::class)
                    // Ausgemusterte Fahrzeuge sind zuschaltbar, aber nicht Standard.
                    ->default(VehicleStatus::Active->value),
                SelectFilter::make('fuel_type')
                    ->label('Antriebsart')
                    ->options(FuelType::class),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export')
                    ->exporter(VehicleExporter::class),
            ])
            // Bewusst kein Mehrfachlöschen: Fahrzeuge werden ausgemustert, nicht
            // gelöscht. Mit zwei Klicks den halben Fuhrpark samt Kostenhistorie zu
            // verlieren, ist ein zu großes Risiko für einen Bequemlichkeitsgewinn.
            // Einzelnes Löschen bleibt auf der Edit-Seite verfügbar (nur Admins,
            // siehe VehiclePolicy::delete).
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
