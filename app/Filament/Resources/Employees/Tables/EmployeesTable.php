<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Filament\Actions\ActivateAction;
use App\Filament\Actions\DeactivateAction;
use App\Filament\Actions\GuardedDeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('last_name')
                    ->label('Nachname')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('Vorname')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department')
                    ->label('Abteilung')
                    ->searchable(),
                TextColumn::make('vehicles_count')
                    ->label('Fahrzeuge')
                    ->counts('vehicles'),
                TextColumn::make('active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktiv' : 'Inaktiv')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('last_name')
            ->filters([
                // Gleiches Muster wie in der Fahrzeugliste: Inaktive sind
                // zuschaltbar, stehen im Alltag aber nicht im Weg.
                TernaryFilter::make('active')
                    ->label('Status')
                    ->placeholder('Alle')
                    ->trueLabel('Nur aktive')
                    ->falseLabel('Nur inaktive')
                    ->default(true),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeactivateAction::make(),
                ActivateAction::make(),
                GuardedDeleteAction::make(),
            ]);
    }
}
