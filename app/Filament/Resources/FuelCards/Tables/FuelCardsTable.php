<?php

namespace App\Filament\Resources\FuelCards\Tables;

use App\Filament\Actions\ActivateAction;
use App\Filament\Actions\DeactivateAction;
use App\Filament\Actions\GuardedDeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FuelCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fuelCardProvider.name')
                    ->label('Anbieter')
                    ->badge()
                    ->sortable(),
                // Die Kartennummer ist verschluesselt und daher nicht durchsuchbar.
                // Die volle Nummer wird bewusst angezeigt (Auftraggeber-Entscheidung):
                // beide Stellen, die sie zeigen, sind nur angemeldeten Nutzern
                // zugaenglich, und die Karten werden ohnehin in dieser Anwendung
                // verwaltet. Der encrypted-Cast schuetzt weiterhin die Datenbank.
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
                SelectFilter::make('fuel_card_provider_id')
                    ->label('Anbieter')
                    ->relationship('fuelCardProvider', 'name'),
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
