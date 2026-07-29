<?php

namespace App\Filament\Resources\FuelCards\Tables;

use App\Enums\FuelCardProvider;
use App\Filament\Actions\GuardedDeleteAction;
use App\Models\FuelCard;
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
                // Auch die Anzeige bleibt sparsam: Anbieter plus die letzten vier
                // Stellen statt der vollen Nummer im Klartext (siehe
                // FuelCard::maskedNumber()); die volle Nummer bleibt dem
                // Bearbeitungsformular vorbehalten.
                TextColumn::make('card_number')
                    ->label('Kartennummer')
                    ->formatStateUsing(fn (FuelCard $record): string => $record->maskedNumber()),
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
