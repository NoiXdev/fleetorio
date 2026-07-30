<?php

namespace App\Filament\Resources\FuelCards\Schemas;

use App\Models\FuelCard;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Zeigt alle erfassten Felder, auch die, die in der Liste fehlen (notes).
 *
 * Die Kartennummer wird vollstaendig angezeigt, nicht maskiert - dieselbe
 * Auftraggeber-Entscheidung wie in FuelCardsTable (siehe Kommentar dort). Der
 * `encrypted`-Cast auf FuelCard::card_number schuetzt weiterhin die
 * Datenbank; er verhindert nicht den Zugriff ueber das Attribut, und das
 * $hidden-Array auf dem Modell wirkt nur auf toArray()/toJson(), nicht auf
 * den direkten Attributzugriff, den eine Infolist-Entry nutzt.
 */
class FuelCardInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tankkarte')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('fuelCardProvider.name')
                            ->label('Anbieter')
                            ->badge(),
                        TextEntry::make('card_number')
                            ->label('Kartennummer'),
                        TextEntry::make('valid_until')
                            ->label('Gültig bis')
                            ->date('d.m.Y')
                            ->placeholder('—'),
                        TextEntry::make('vehicles_count')
                            ->label('Fahrzeuge')
                            ->state(fn (FuelCard $record): string => (string) $record->vehicles()->count()),
                    ]),

                Section::make('Notizen')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notizen')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
