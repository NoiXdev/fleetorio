<?php

use App\Filament\Pages\AppearanceSettings;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('lets an admin reach the appearance settings page', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $this->get('/admin/appearance')->assertOk();
});

it('hides the appearance settings page from a read-only user, both in navigation and by direct call', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    expect(AppearanceSettings::canAccess())->toBeFalse();

    $this->get('/admin')->assertDontSee('Erscheinungsbild');
    $this->get('/admin/appearance')->assertForbidden();
});

it('shows the appearance settings page to an admin in navigation', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $this->get('/admin')->assertSee('Erscheinungsbild');
});

it('prefills the form with the current settings, defaults when nothing is saved', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    livewire(AppearanceSettings::class)
        ->assertFormSet([
            'brand_name' => 'Fleetorio',
            'primary_color' => '#2563eb',
            'logo' => null,
        ]);
});

it('lets an admin save name, color and logo, and the change is visible immediately', function () {
    Storage::fake('local');

    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $logo = UploadedFile::fake()->image('logo.png', 200, 200)->size(50);

    livewire(AppearanceSettings::class)
        ->fillForm([
            'brand_name' => 'Testflotte',
            'primary_color' => '#00aa00',
            'logo' => $logo,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $setting = AppSetting::query()->findOrFail(AppSetting::SINGLETON_ID);

    expect($setting->brand_name)->toBe('Testflotte')
        ->and($setting->primary_color)->toBe('#00aa00')
        ->and($setting->logo_path)->not->toBeNull();

    Storage::disk('local')->assertExists($setting->logo_path);

    // Kein Neustart noetig - der Zwischenspeicher wurde geleert.
    expect(AppSetting::name())->toBe('Testflotte');

    // Abmelden, da die Login-Seite einen bereits angemeldeten Benutzer
    // sonst zum Dashboard weiterleitet (302), bevor der Titel geprueft
    // werden kann.
    auth()->logout();

    $this->get('/admin/login')->assertOk()->assertSee('Testflotte');
    $this->get('/')->assertOk()->assertSee('Testflotte');
});

it('rejects an invalid hex color', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    livewire(AppearanceSettings::class)
        ->fillForm([
            'brand_name' => 'Testflotte',
            'primary_color' => 'not-a-color',
        ])
        ->call('save')
        ->assertHasFormErrors(['primary_color']);

    // Die Anwendung bleibt trotzdem benutzbar - es wurde nichts gespeichert.
    expect(AppSetting::color())->toBe('#2563eb');
});

it('rejects a logo file that is too large', function () {
    Storage::fake('local');

    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $oversized = UploadedFile::fake()->image('zu-gross.png')->size(3 * 1024);

    livewire(AppearanceSettings::class)
        ->fillForm([
            'brand_name' => 'Testflotte',
            'primary_color' => '#2563eb',
            'logo' => $oversized,
        ])
        ->call('save')
        ->assertHasFormErrors(['logo']);
});

it('rejects a logo file with a disallowed format', function () {
    Storage::fake('local');

    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $wrongFormat = UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf');

    livewire(AppearanceSettings::class)
        ->fillForm([
            'brand_name' => 'Testflotte',
            'primary_color' => '#2563eb',
            'logo' => $wrongFormat,
        ])
        ->call('save')
        ->assertHasFormErrors(['logo']);
});

it('rejects an svg logo containing a script', function () {
    Storage::fake('local');

    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $malicious = UploadedFile::fake()->createWithContent(
        'logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>',
    );

    livewire(AppearanceSettings::class)
        ->fillForm([
            'brand_name' => 'Testflotte',
            'primary_color' => '#2563eb',
            'logo' => $malicious,
        ])
        ->call('save')
        ->assertHasFormErrors(['logo']);
});

it('accepts a clean svg logo', function () {
    Storage::fake('local');

    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $clean = UploadedFile::fake()->createWithContent(
        'logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4" /></svg>',
    );

    livewire(AppearanceSettings::class)
        ->fillForm([
            'brand_name' => 'Testflotte',
            'primary_color' => '#2563eb',
            'logo' => $clean,
        ])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('falls back to the default logo again once the logo is removed', function () {
    Storage::fake('local');

    AppSetting::factory()->create(['logo_path' => 'branding/old-logo.svg']);
    Storage::disk('local')->put('branding/old-logo.svg', '<svg></svg>');

    actingAs(User::factory()->admin()->withTwoFactor()->create());

    livewire(AppearanceSettings::class)
        ->fillForm([
            'brand_name' => 'Testflotte',
            'primary_color' => '#2563eb',
            'logo' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(AppSetting::hasCustomLogo())->toBeFalse()
        ->and(AppSetting::logoUrl())->toBe(asset('images/fleetorio-logo.svg'));
});

describe('colour notations', function () {
    // Die frueheren strengen Regeln wiesen naheliegende Eingaben mit einer
    // Fehlermeldung ab - das war der vom Auftraggeber gemeldete Fehler beim
    // Aendern der Farbe. Jetzt wird normalisiert, was eindeutig gemeint ist.
    it('accepts a hex colour written without the leading hash', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        livewire(AppearanceSettings::class)
            ->fillForm(['brand_name' => 'T', 'primary_color' => 'bb0000'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(AppSetting::query()->first()->primary_color)->toBe('#bb0000');
    });

    it('expands the three digit short form the way css does', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        livewire(AppearanceSettings::class)
            ->fillForm(['brand_name' => 'T', 'primary_color' => '#fff'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(AppSetting::query()->first()->primary_color)->toBe('#ffffff');
    });

    it('tolerates uppercase and surrounding whitespace', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        livewire(AppearanceSettings::class)
            ->fillForm(['brand_name' => 'T', 'primary_color' => '  #BB0000 '])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(AppSetting::query()->first()->primary_color)->toBe('#bb0000');
    });

    it('still rejects something that is not a colour at all', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        livewire(AppearanceSettings::class)
            ->fillForm(['brand_name' => 'T', 'primary_color' => 'unfug'])
            ->call('save')
            ->assertHasFormErrors(['primary_color']);

        expect(AppSetting::query()->count())->toBe(0);
    });

    // Ohne Rueckfall lieferte buildPalette() bei unbrauchbaren Eingaben keinen
    // Fehler, sondern falsche Werte: "#fff" ergab ein Rot statt Weiss. Ein
    // stiller Fehler ist schlimmer als ein laut scheiternder.
    it('falls back to the default colour instead of producing a wrong palette', function () {
        $fromGarbage = AppSetting::buildPalette('unfug');
        $fromDefault = AppSetting::buildPalette(AppSetting::DEFAULT_COLOR);

        expect($fromGarbage)->toBe($fromDefault);
    });

    it('builds the same palette regardless of how the colour was written', function () {
        expect(AppSetting::buildPalette('bb0000'))
            ->toBe(AppSetting::buildPalette('#BB0000'));
    });
});
