<?php

namespace App\Filament\Widgets;

use App\Models\Vehicle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ExpiringLeases extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return 'Leasingende in den nächsten '.Vehicle::LEASE_WARNING_DAYS.' Tagen';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Vehicle::leaseEndingWithin(Vehicle::LEASE_WARNING_DAYS))
            ->defaultSort('lease_end')
            ->emptyStateHeading('Kein Vertrag läuft in diesem Zeitraum aus')
            ->columns([
                TextColumn::make('license_plate')
                    ->label('Kennzeichen'),
                TextColumn::make('brand')
                    ->label('Fahrzeug')
                    ->formatStateUsing(fn ($state, Vehicle $record): string => "{$record->brand} {$record->model}"),
                TextColumn::make('employee.last_name')
                    ->label('Fahrer')
                    ->formatStateUsing(fn ($state, Vehicle $record): string => $record->employee?->full_name ?? '—'),
                TextColumn::make('lease_end')
                    ->label('Leasingende')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('days_left')
                    ->label('Resttage')
                    ->badge()
                    ->state(fn (Vehicle $record): int => (int) today()->diffInDays($record->lease_end, false))
                    // Gleiche Schwellenlogik wie die Leasingende-Spalte in
                    // VehiclesTable, damit Farbe = Dringlichkeit überall
                    // dasselbe bedeutet. gray/success sind hier nicht
                    // erreichbar (die Abfrage liefert nur 0..LEASE_WARNING_DAYS
                    // Resttage), aber die Logik bleibt bewusst identisch statt
                    // eine eigene, engere Variante zu pflegen.
                    ->color(fn (int $state): string => match (true) {
                        $state < 0 => 'gray',
                        $state <= 90 => 'danger',
                        $state <= Vehicle::LEASE_WARNING_DAYS => 'warning',
                        default => 'success',
                    }),
            ]);
    }
}
