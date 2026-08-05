<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Rolle')
                    ->badge(),
                IconColumn::make('can_view_costs')
                    ->label('Kosten')
                    ->boolean(),
                IconColumn::make('app_authentication_secret')
                    ->label('2FA aktiv')
                    ->boolean()
                    ->state(fn ($record): bool => filled($record->app_authentication_secret)),
                IconColumn::make('requires_two_factor')
                    ->label('2FA Pflicht')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->label('Rolle')
                    ->options(UserRole::class),
            ])
            ->recordActions([
                EditAction::make(),
                // Ein Admin kann fremdes 2FA nur ENTFERNEN, nie einrichten -
                // das Geheimnis darf nur in der Authenticator-App des
                // Benutzers entstehen (siehe UserPolicy::resetTwoFactor).
                // Sichtbar nur, wenn ueberhaupt etwas zurueckzusetzen ist.
                Action::make('resetTwoFactor')
                    ->label('2FA zurücksetzen')
                    ->icon('heroicon-m-shield-exclamation')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Zwei-Faktor-Authentifizierung zurücksetzen')
                    ->modalDescription(
                        'Der Benutzer muss die Authentifizierung danach neu einrichten. '
                        .'Bisherige Wiederherstellungscodes werden ungültig.'
                    )
                    ->modalSubmitActionLabel('Zurücksetzen')
                    ->visible(fn (User $record): bool => filled($record->app_authentication_secret)
                        && auth()->user()->can('resetTwoFactor', $record))
                    ->action(function (User $record): void {
                        $record->forceFill([
                            'app_authentication_secret' => null,
                            'app_authentication_recovery_codes' => null,
                        ])->save();

                        Notification::make()
                            ->success()
                            ->title('2FA zurückgesetzt')
                            ->send();
                    }),
                DeleteAction::make(),
            ]);
    }
}
