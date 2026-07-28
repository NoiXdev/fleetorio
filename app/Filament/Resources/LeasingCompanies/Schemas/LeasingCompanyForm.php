<?php

namespace App\Filament\Resources\LeasingCompanies\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LeasingCompanyForm
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
                Textarea::make('notes')
                    ->label('Notizen')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
