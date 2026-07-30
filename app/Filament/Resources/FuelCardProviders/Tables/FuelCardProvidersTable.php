<?php

namespace App\Filament\Resources\FuelCardProviders\Tables;

use App\Filament\Actions\GuardedDeleteAction;
use App\Models\FuelCard;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FuelCardProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('fuel_cards_count')
                    ->label('Tankkarten')
                    ->counts('fuelCards'),
            ])
            ->defaultSort('name')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                // Beim Tankkartenanbieter ist die naechste Stufe eine
                // Tankkarte, kein Fahrzeug - siehe Kommentar an
                // GuardedDeleteAction.
                GuardedDeleteAction::make()
                    ->guardedBy(
                        'fuelCards',
                        fn (FuelCard $fuelCard): string => $fuelCard->card_number,
                        fn (int $count, string $label): string => $count === 1
                            ? "Der Eintrag ist noch der Tankkarte {$label} zugeordnet."
                            : "Der Eintrag ist noch {$count} Tankkarten zugeordnet, unter anderem {$label}.",
                    ),
            ]);
    }
}
