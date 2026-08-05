<?php

namespace App\Filament\Resources\Dealerships\Schemas;

use App\Models\Dealership;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Zeigt alle erfassten Felder, auch die, die in der Liste fehlen (email,
 * street, postcode, notes) - gerade beim Autohaus aergerlich, weil es genau
 * dafuer angelegt wurde, festzuhalten, wo der Service gemacht wird.
 *
 * Die Adresse wird als ein zusammenhaengendes Feld dargestellt (Strasse in
 * einer Zeile, PLZ und Ort darunter zusammen), nicht als drei lose Felder -
 * siehe self::addressLines().
 */
class DealershipInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Autohaus')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name'),
                        TextEntry::make('vehicles_count')
                            ->label('Fahrzeuge')
                            ->state(fn (Dealership $record): string => (string) $record->vehicles()->count()),
                    ]),

                Section::make('Kontakt')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('contact_person')
                            ->label('Ansprechpartner')
                            ->placeholder('—'),
                        TextEntry::make('phone')
                            ->label('Telefon')
                            ->placeholder('—'),
                        TextEntry::make('email')
                            ->label('E-Mail')
                            ->placeholder('—'),
                    ]),

                Section::make('Adresse')
                    ->schema([
                        TextEntry::make('address')
                            ->label('Adresse')
                            ->state(fn (Dealership $record): ?array => self::addressLines($record))
                            ->listWithLineBreaks()
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Notizen')
                    ->schema([
                        IconEntry::make('active')
                            ->label('Aktiv')
                            ->boolean(),
                        TextEntry::make('notes')
                            ->label('Notizen')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Strasse als eine Zeile, PLZ und Ort zusammen als zweite Zeile. Sind
     * alle drei Angaben leer, liefert die Methode null, damit der
     * Placeholder ("—") greift statt zweier leerer Zeilen.
     *
     * @return list<string>|null
     */
    private static function addressLines(Dealership $record): ?array
    {
        $street = trim((string) $record->street);
        $cityLine = trim(trim(($record->postcode ?? '').' '.($record->city ?? '')));

        $lines = array_values(array_filter([$street, $cityLine], fn (string $line): bool => $line !== ''));

        return $lines === [] ? null : $lines;
    }
}
