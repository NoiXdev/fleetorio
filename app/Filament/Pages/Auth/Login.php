<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
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
        return 'Login zum Fuhrparkmanagement';
    }
}
