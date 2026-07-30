<?php

use App\Models\AppSetting;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Schema;

it('falls back to the documented defaults when nothing has been saved', function () {
    expect(AppSetting::name())->toBe('Fleetorio')
        ->and(AppSetting::color())->toBe('#2563eb')
        ->and(AppSetting::hasCustomLogo())->toBeFalse()
        ->and(AppSetting::logoUrl())->toBe(asset('images/fleetorio-logo.svg'));
});

it('reads saved values instead of the defaults', function () {
    AppSetting::factory()->create([
        'brand_name' => 'Testflotte',
        'logo_path' => 'branding/custom-logo.svg',
        'primary_color' => '#00aa00',
    ]);

    expect(AppSetting::name())->toBe('Testflotte')
        ->and(AppSetting::color())->toBe('#00aa00')
        ->and(AppSetting::hasCustomLogo())->toBeTrue()
        ->and(AppSetting::logoUrl())->toBe(route('branding.logo'));
});

it('caches the settings so a saved change is not read from the database on every call', function () {
    AppSetting::factory()->create(['brand_name' => 'Erstwert']);

    expect(AppSetting::name())->toBe('Erstwert');

    // Direkt an der Datenbank vorbei geaendert, ohne den Cache zu leeren -
    // AppSetting::name() darf das nicht sehen, solange der Zwischenspeicher
    // nicht explizit geleert wurde (siehe naechster Test).
    AppSetting::query()->where('id', AppSetting::SINGLETON_ID)->update(['brand_name' => 'Unbemerkt']);

    expect(AppSetting::name())->toBe('Erstwert');
});

it('picks up a change immediately once the cache has been cleared', function () {
    AppSetting::factory()->create(['brand_name' => 'Erstwert']);
    expect(AppSetting::name())->toBe('Erstwert');

    AppSetting::query()->where('id', AppSetting::SINGLETON_ID)->update(['brand_name' => 'Neuwert']);
    AppSetting::forgetCache();

    expect(AppSetting::name())->toBe('Neuwert');
});

/*
 * Der wichtigste Test dieser Aufgabe: AdminPanelProvider::panel() liest
 * AppSetting::* bei jedem Boot-Zyklus der Anwendung, also praktisch bei
 * jedem Request und jedem artisan-Aufruf - auch bei der allerersten
 * `migrate`, die die Tabelle ueberhaupt erst anlegt. Ohne Ausfallschutz
 * waere ein frischer Klon dieses Projekts nicht mehr migrierbar.
 */
it('keeps artisan and the panel usable when the app_settings table does not exist', function () {
    $migration = include database_path('migrations/2026_07_30_170000_create_app_settings_table.php');

    Schema::drop('app_settings');

    try {
        expect(Schema::hasTable('app_settings'))->toBeFalse();

        // Ein beliebiger artisan-Aufruf boot-strappt die Anwendung
        // vollstaendig, inklusive AdminPanelProvider::panel().
        $this->artisan('list')->assertSuccessful();

        // Und ein tatsaechlicher Seitenaufruf durch den Panel-Provider.
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Fleetorio');

        expect(AppSetting::name())->toBe('Fleetorio')
            ->and(AppSetting::color())->toBe('#2563eb')
            ->and(AppSetting::hasCustomLogo())->toBeFalse();
    } finally {
        // DROP TABLE committet bei MariaDB/InnoDB implizit und durchbricht
        // damit die Transaktion, in die RefreshDatabase diesen Test sonst
        // einwickelt - ein Rollback am Testende wuerde den Drop nicht
        // rueckgaengig machen. Deshalb hier aktiv wiederherstellen, sonst
        // fehlt die Tabelle allen nachfolgenden Tests im selben Lauf.
        $migration->up();
    }
});

it('builds a palette whose shade 600 is exactly the configured color', function (string $hex) {
    $palette = AppSetting::buildPalette($hex);

    expect($palette)->toHaveKeys([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950])
        ->and(Color::convertToHex($palette[600]))->toBe($hex);
})->with([
    '#2563eb',
    '#bb0000',
    '#22c55e',
    '#000000',
    '#ffffff',
]);

it('keeps the palette monotonically getting darker from shade 50 to 950', function () {
    $palette = AppSetting::buildPalette('#2563eb');

    $lightnesses = collect($palette)
        ->map(fn (string $oklch): float => (float) sscanf($oklch, 'oklch(%f %f %f)')[0])
        ->values()
        ->all();

    $sorted = $lightnesses;
    rsort($sorted);

    expect($lightnesses)->toBe($sorted);
});
