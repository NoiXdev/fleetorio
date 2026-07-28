<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->label('Vorname')
                    ->required()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->label('Nachname')
                    ->required()
                    ->maxLength(255),
                TextInput::make('department')
                    ->label('Abteilung')
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('E-Mail')
                    ->email()
                    ->maxLength(255),
                Toggle::make('active')
                    ->label('Aktiv')
                    ->default(true),
            ]);
    }
}
