<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Models\AppSetting;
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
            // Name, Logo/Favicon und Primärfarbe kommen aus den über die
            // Oberfläche pflegbaren Einstellungen (App\Filament\Pages\
            // AppearanceSettings), mit den dortigen Konstanten als
            // Rückfallebene. WICHTIG: panel() hier läuft bei jedem
            // Boot-Zyklus der Anwendung neu (nicht nur einmal beim Start),
            // also praktisch bei jedem Request und jedem artisan-Aufruf.
            // AppSetting::* ist deshalb zwischengespeichert und verträgt
            // eine fehlende oder unerreichbare Datenbank, ohne zu brechen -
            // siehe die Kommentare dort für Details.
            ->brandName(AppSetting::name())
            ->brandLogo(AppSetting::logoUrl())
            ->brandLogoHeight('2rem')
            ->favicon(AppSetting::logoUrl())
            // Die Palette steht hier ausgeschrieben (statt Color::hex()),
            // weil Color::hex() aus der Eingabefarbe nur den Farbwinkel
            // übernimmt und Helligkeit/Sättigung durch eine feste Kurve
            // ersetzt - Stufe 600, die Filament in Schaltflächen am
            // häufigsten verwendet, träfe die gewählte Farbe dadurch
            // praktisch nie exakt. AppSetting::buildPalette() skaliert
            // stattdessen Filaments eigene Kurve so, dass Stufe 600 exakt
            // die eingestellte Primärfarbe ist - siehe dortiger Kommentar
            // für die Herleitung der übrigen Stufen.
            //
            // 'danger' bleibt auf Rose verschoben, unabhängig von der
            // gewählten Primärfarbe: Filament nutzt es für
            // Löschbestätigungen, Fehler und Warnungen. Wählte ein
            // Administrator zufällig eine rötliche Primärfarbe, wäre ein
            // "Speichern"-Knopf sonst von einem "Löschen"-Knopf kaum zu
            // unterscheiden. 'warning' und 'success' bleiben unverändert.
            ->colors([
                'primary' => AppSetting::palette(),
                'danger' => Color::Rose,
            ])
            ->login(Login::class)
            ->passwordReset()
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->brandName(AppSetting::name()),
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
