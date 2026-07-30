<?php

namespace App\Filament\Pages\Auth;

use App\Models\AppSetting;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    /**
     * Nur die Überschrift wird angepasst. `authenticate()` bleibt
     * unverändert im Elternteil, damit das eingebaute Rate-Limiting
     * (5 Versuche, 60 Sekunden Sperre) erhalten bleibt.
     */
    public function getHeading(): string | Htmlable | null
    {
        return 'Login zum '.AppSetting::name();
    }

    /**
     * Wie im Elternteil, aber mit `autocomplete="username"` statt dem
     * unspezifischen `autocomplete="on"`, das Filaments `->autocomplete()`
     * ohne Argument erzeugt.
     *
     * Hintergrund: "on" teilt einem Passwortmanager nur mit, dass ausgefüllt
     * werden darf - nicht, welcher Wert gemeint ist. Der Manager muss dann
     * anhand von `type="email"` raten. Zusammen mit `autofocus` und der
     * DOM-Aktualisierung durch Livewire greifen manche Manager mehrfach zu und
     * hängen den Wert an den bereits eingetragenen an - im Feld steht die
     * Adresse dann zwei- oder dreifach. "username" ist der Standardwert für
     * das Kennungsfeld eines Anmeldeformulars und macht die Absicht eindeutig.
     */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('filament-panels::auth/pages/login.form.email.label'))
            ->email()
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }
}
