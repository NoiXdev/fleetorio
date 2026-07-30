<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Filament\Actions\GuardedDeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                IconColumn::make('active')
                    ->label('Aktiv')
                    ->boolean(),
            ])
            ->defaultSort('last_name')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                GuardedDeleteAction::make(),
            ]);
    }
}
