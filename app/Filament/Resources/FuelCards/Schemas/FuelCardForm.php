<?php

namespace App\Filament\Resources\FuelCards\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FuelCardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('fuel_card_provider_id')
                    ->label('Anbieter')
                    ->relationship('fuelCardProvider', 'name')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->placeholder('Auswählen')
                    ->required(),
                TextInput::make('card_number')
                    ->label('Kartennummer')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Wird verschlüsselt gespeichert.'),
                DatePicker::make('valid_until')
                    ->label('Gültig bis')
                    ->native(false)
                    ->displayFormat('d.m.Y'),
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
