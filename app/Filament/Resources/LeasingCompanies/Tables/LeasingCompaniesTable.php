<?php

namespace App\Filament\Resources\LeasingCompanies\Tables;

use App\Filament\Actions\GuardedDeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeasingCompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_person')
                    ->label('Ansprechpartner')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefon'),
                TextColumn::make('vehicles_count')
                    ->label('Fahrzeuge')
                    ->counts('vehicles'),
            ])
            ->defaultSort('name')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                GuardedDeleteAction::make(),
            ]);
    }
}
