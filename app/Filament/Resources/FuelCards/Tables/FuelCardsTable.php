<?php

namespace App\Filament\Resources\FuelCards\Tables;

use App\Enums\FuelCardProvider;
use App\Filament\Actions\GuardedDeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FuelCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider')
                    ->label('Anbieter')
                    ->badge()
                    ->sortable(),
                // Die Kartennummer ist verschlüsselt und daher nicht durchsuchbar.
                TextColumn::make('card_number')
                    ->label('Kartennummer'),
                TextColumn::make('valid_until')
                    ->label('Gültig bis')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('vehicles_count')
                    ->label('Fahrzeuge')
                    ->counts('vehicles'),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->label('Anbieter')
                    ->options(FuelCardProvider::class),
            ])
            ->recordActions([
                EditAction::make(),
                GuardedDeleteAction::make(),
            ]);
    }
}
