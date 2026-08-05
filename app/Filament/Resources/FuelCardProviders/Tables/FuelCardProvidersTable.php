<?php

namespace App\Filament\Resources\FuelCardProviders\Tables;

use App\Filament\Actions\ActivateAction;
use App\Filament\Actions\DeactivateAction;
use App\Filament\Actions\GuardedDeleteAction;
use App\Models\FuelCard;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
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
                TextColumn::make('active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktiv' : 'Inaktiv')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('name')
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
                // Beim Tankkartenanbieter ist die naechste Stufe eine
                // Tankkarte, kein Fahrzeug - siehe Kommentar an
                // GuardedDeleteAction.
                DeactivateAction::make(),
                ActivateAction::make(),
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
