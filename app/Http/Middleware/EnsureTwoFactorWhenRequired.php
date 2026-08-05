<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Erzwingt die 2FA-Einrichtung fuer Benutzer mit requires_two_factor.
 *
 * Warum eigene Middleware statt Filaments isRequired: Dessen Wert wird beim
 * Registrieren der Routen ausgewertet (Filament\Pages\Concerns\HasRoutes
 * entscheidet dort, ob die eingebaute Middleware ueberhaupt an die Route
 * gehaengt wird). Zu diesem Zeitpunkt ist kein Benutzer angemeldet, eine
 * benutzerabhaengige Entscheidung ist dort also nicht moeglich.
 */
class EnsureTwoFactorWhenRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $user->requires_two_factor || filled($user->app_authentication_secret)) {
            return $next($request);
        }

        // Zielseite und Abmeldeweg muessen erreichbar bleiben, sonst leitete
        // die Middleware auf eine Seite um, die sie erneut umleitet - eine
        // Schleife, aus der sich der Benutzer nicht einmal abmelden koennte.
        // Livewire-Requests tragen das Formular der Profilseite: wuerden sie
        // umgeleitet, koennte 2FA dort nicht abgesendet werden.
        if ($request->routeIs('filament.admin.auth.profile', 'filament.admin.auth.logout')
            || $request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        return redirect()->route('filament.admin.auth.profile');
    }
}
