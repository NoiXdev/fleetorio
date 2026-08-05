<?php

namespace App\Filament\Resources\LeasingCompanies\Schemas;

use App\Models\LeasingCompany;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Zeigt alle erfassten Felder, auch die, die in der Liste fehlen (email,
 * notes).
 */
class LeasingCompanyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kontakt')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name'),
                        TextEntry::make('vehicles_count')
                            ->label('Fahrzeuge')
                            ->state(fn (LeasingCompany $record): string => (string) $record->vehicles()->count()),
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
}
