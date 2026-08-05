<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('E-Mail')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                // Beim Bearbeiten leer lassen heisst: Passwort unveraendert.
                // Der 'hashed'-Cast am Model uebernimmt das Hashen.
                // requiredOn(Operation::Create) loest in dieser Filament-Version
                // nicht auf (BadMethodCallException) - deshalb der Closure-Fallback.
                TextInput::make('password')
                    ->label('Passwort')
                    ->password()
                    ->revealable()
                    ->minLength(12)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Beim Bearbeiten leer lassen, um das Passwort beizubehalten.'),
                Select::make('role')
                    ->label('Rolle')
                    ->options(UserRole::class)
                    ->native(false)
                    ->default(UserRole::Viewer->value)
                    ->required(),
                Toggle::make('can_view_costs')
                    ->label('Darf Kosten sehen')
                    ->helperText('Unabhängig von der Rolle. Ohne dieses Recht bleiben alle Beträge verborgen.'),
                Toggle::make('requires_two_factor')
                    ->label('2FA verpflichtend')
                    ->helperText('Der Benutzer wird beim nächsten Aufruf des Panels auf die Profilseite geleitet und '
                        .'kann die Anwendung erst nach Einrichtung von 2FA benutzen.'),
            ]);
    }
}
