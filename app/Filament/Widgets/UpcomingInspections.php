<?php

namespace App\Filament\Widgets;

use App\Models\Vehicle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingInspections extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return 'HU fällig in den nächsten '.Vehicle::INSPECTION_WARNING_DAYS.' Tagen';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Vehicle::inspectionDueWithin(Vehicle::INSPECTION_WARNING_DAYS))
            ->defaultSort('inspection_valid_until')
            ->emptyStateHeading('Keine HU steht in diesem Zeitraum an')
            ->columns([
                TextColumn::make('license_plate')
                    ->label('Kennzeichen'),
                TextColumn::make('brand')
                    ->label('Fahrzeug')
                    ->formatStateUsing(fn ($state, Vehicle $record): string => "{$record->brand} {$record->model}"),
                TextColumn::make('employee.last_name')
                    ->label('Fahrer')
                    ->formatStateUsing(fn ($state, Vehicle $record): string => $record->employee?->full_name ?? '—'),
                TextColumn::make('inspection_valid_until')
                    ->label('HU gültig bis')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('days_left')
                    ->label('Resttage')
                    ->badge()
                    ->state(fn (Vehicle $record): int => (int) today()->diffInDays($record->inspection_valid_until, false))
                    // Eigene Skala statt Vehicle::leaseUrgencyColor(): die
                    // Leasing-Schwellen (90/180 Tage) passen nicht zu diesem
                    // 60-Tage-Fenster.
                    ->color(fn (int $state): string => Vehicle::inspectionUrgencyColor($state)),
            ]);
    }
}
