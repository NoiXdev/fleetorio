<?php

namespace App\Filament\Resources\FuelCardProviders\Schemas;

use App\Models\FuelCardProvider;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FuelCardProviderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tankkartenanbieter')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name'),
                        TextEntry::make('fuel_cards_count')
                            ->label('Tankkarten')
                            ->state(fn (FuelCardProvider $record): string => (string) $record->fuelCards()->count()),
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
