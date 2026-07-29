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
            // Primärfarbe ist das Markenrot (#bb0000). Filament nutzt
            // standardmäßig Rot auch für 'danger' (Löschbestätigungen,
            // Fehler, Warnungen) - mit rotem Primärton wäre ein
            // "Speichern"-Knopf nicht mehr von einem "Löschen"-Knopf zu
            // unterscheiden. Deshalb ist 'danger' auf Color::Rose verschoben:
            // deutlich pinker und heller als das Markenrot, aber weiterhin
            // klar als Warnfarbe erkennbar. 'warning' und 'success' bleiben
            // unverändert.
            ->colors([
                'primary' => Color::hex('#bb0000'),
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
