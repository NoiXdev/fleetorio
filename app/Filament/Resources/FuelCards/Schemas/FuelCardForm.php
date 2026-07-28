<?php

namespace App\Filament\Resources\FuelCards\Schemas;

use App\Enums\FuelCardProvider;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FuelCardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('provider')
                    ->label('Anbieter')
                    ->options(FuelCardProvider::class)
                    ->native(false)
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
                Textarea::make('notes')
                    ->label('Notizen')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
