<?php

namespace App\Filament\Resources\Dealerships\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DealershipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('contact_person')
                    ->label('Ansprechpartner')
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Telefon')
                    ->tel()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('E-Mail')
                    ->email()
                    ->maxLength(255),
                TextInput::make('street')
                    ->label('Straße')
                    ->maxLength(255),
                TextInput::make('postcode')
                    ->label('PLZ')
                    ->maxLength(255),
                TextInput::make('city')
                    ->label('Ort')
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
