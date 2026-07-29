<?php

namespace App\Filament\Resources\Dealerships\Tables;

use App\Filament\Actions\GuardedDeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DealershipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city')
                    ->label('Ort')
                    ->searchable(),
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
                EditAction::make(),
                GuardedDeleteAction::make(),
            ]);
    }
}
