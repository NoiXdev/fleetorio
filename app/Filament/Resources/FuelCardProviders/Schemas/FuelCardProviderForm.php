<?php

namespace App\Filament\Resources\FuelCardProviders\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FuelCardProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Toggle::make('active')
                    ->label('Aktiv')
                    ->default(true)
                    ->helperText('Inaktive Einträge tauchen nicht mehr in Auswahllisten auf. Gelöscht werden kann nur, was inaktiv ist.'),
                Textarea::make('notes')
                    ->label('Notizen')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
