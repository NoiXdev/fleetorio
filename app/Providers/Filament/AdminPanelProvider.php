<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('3B Fuhrparkmanagement')
            ->brandLogo(asset('images/a3b-logo.svg'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/a3b-logo.png'))
            // Primärfarbe ist das Markenrot #bb0000 = oklch(0.497 0.204 29.234).
            //
            // Die Palette steht hier ausgeschrieben, weil Color::hex() sie nicht
            // treffen kann: Diese Methode übernimmt aus der Eingabefarbe nur den
            // Farbwinkel und ersetzt Helligkeit und Sättigung durch feste Werte
            // (siehe Color::generatePalette()). Für Stufe 600 - die Filament in
            // Schaltflächen am häufigsten verwendet - käme oklch(0.598 0.169 …)
            // heraus: heller und blasser als das Markenrot.
            //
            // Deshalb liegt #bb0000 unten exakt auf Stufe 600. Die helleren
            // Stufen behalten Filaments Kurve (sie dienen Hintergründen und
            // Abzeichen und sollen hell bleiben), die Stufen ab 400 führen zum
            // Markenrot hin und darüber hinaus ins Dunkle.
            //
            // 'danger' ist auf Rose verschoben: Filament nutzt es für
            // Löschbestätigungen, Fehler und Warnungen. Bliebe es rot, wäre ein
            // "Speichern"-Knopf nicht von einem "Löschen"-Knopf zu
            // unterscheiden. 'warning' und 'success' bleiben unverändert.
            ->colors([
                'primary' => [
                    50 => 'oklch(0.977 0.014 29.234)',
                    100 => 'oklch(0.950 0.033 29.234)',
                    200 => 'oklch(0.905 0.063 29.234)',
                    300 => 'oklch(0.840 0.106 29.234)',
                    400 => 'oklch(0.740 0.170 29.234)',
                    500 => 'oklch(0.620 0.195 29.234)',
                    600 => 'oklch(0.497 0.204 29.234)', // #bb0000
                    700 => 'oklch(0.440 0.185 29.234)',
                    800 => 'oklch(0.390 0.160 29.234)',
                    900 => 'oklch(0.345 0.135 29.234)',
                    950 => 'oklch(0.250 0.090 29.234)',
                ],
                'danger' => Color::Rose,
            ])
            ->login(Login::class)
            ->passwordReset()
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->brandName('3B Fuhrparkmanagement'),
            ], isRequired: false)
            ->navigationGroups([
                'Fuhrpark',
                'Stammdaten',
                'Verwaltung',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
