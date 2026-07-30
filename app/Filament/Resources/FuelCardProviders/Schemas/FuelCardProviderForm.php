<?php

namespace App\Filament\Resources\FuelCardProviders\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                Textarea::make('notes')
                    ->label('Notizen')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
