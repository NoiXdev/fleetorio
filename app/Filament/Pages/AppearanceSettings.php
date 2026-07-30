<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Rules\RejectsUnsafeSvg;
use BackedEnum;
use Closure;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * Macht Name, Logo und Primaerfarbe ueber die Oberflaeche pflegbar (siehe
 * App\Models\AppSetting fuer Zwischenspeicher, Ausfallschutz und die
 * Farbpalette-Herleitung). Nur fuer Administratoren, siehe canAccess() unten
 * und den zugehoerigen Test in
 * tests/Feature/Filament/AppearanceSettingsTest.php, der sowohl die
 * Navigation als auch den direkten Aufruf fuer einen Nur-Lese-Benutzer
 * prueft.
 */
class AppearanceSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $title = 'Erscheinungsbild';

    protected static ?string $slug = 'appearance';

    protected string $view = 'filament.pages.appearance-settings';

    /**
     * Ein Logo braucht nicht mehr; deckungsgleich mit der Vorgabe in der
     * Spezifikation.
     */
    private const MAX_LOGO_SIZE_KB = 2 * 1024;

    private const ACCEPTED_LOGO_MIME_TYPES = ['image/svg+xml', 'image/png', 'image/webp'];

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'brand_name' => AppSetting::name(),
            'logo' => AppSetting::logoStoragePath(),
            'primary_color' => AppSetting::color(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('brand_name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                FileUpload::make('logo')
                    ->label('Logo')
                    ->disk('local')
                    ->directory('branding')
                    ->acceptedFileTypes(self::ACCEPTED_LOGO_MIME_TYPES)
                    ->maxSize(self::MAX_LOGO_SIZE_KB)
                    ->helperText('Leer lassen für das Standardlogo. Erlaubt: SVG, PNG, WebP, höchstens '.(self::MAX_LOGO_SIZE_KB / 1024).' MB.')
                    ->rules([new RejectsUnsafeSvg])
                    // Deutsche Fehlermeldungen: siehe gleiches Muster bei
                    // VehicleForm/VehicleDocumentsRelationManager - weder
                    // Laravels eigene validation.php noch Filaments deutsche
                    // Uebersetzung decken "mimetypes" und "max" ab.
                    ->validationMessages([
                        'mimetypes' => 'Es sind nur SVG-, PNG- und WebP-Dateien erlaubt.',
                        'max' => 'Das Logo darf höchstens '.(self::MAX_LOGO_SIZE_KB / 1024).' MB groß sein.',
                    ]),
                ColorPicker::make('primary_color')
                    ->label('Primärfarbe')
                    ->required()
                    // Eingaben werden normalisiert, nicht abgelehnt: "bb0000"
                    // ohne Raute, die Kurzform "#fff" oder ein versehentliches
                    // Leerzeichen meinen offensichtlich eine Farbe. Die frühere
                    // strenge Prüfung wies genau das mit einer Fehlermeldung ab.
                    // Erst wenn nach dem Normalisieren keine Farbe übrig bleibt,
                    // greift die Regel und meldet es.
                    ->dehydrateStateUsing(fn (?string $state): ?string => AppSetting::normalizeColor($state) ?? $state)
                    ->rule(function (): Closure {
                        return function (string $attribute, mixed $value, Closure $fail): void {
                            if (AppSetting::normalizeColor(is_string($value) ? $value : null) === null) {
                                $fail('Bitte eine Hex-Farbe angeben, z. B. #2563eb oder bb0000.');
                            }
                        };
                    }),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        // getState() validiert ueber die oben angegebenen Regeln (siehe
        // Filament\Schemas\Concerns\HasState::getState(), das intern
        // $this->validate() aufruft) - eine ungueltige Farbe oder ein zu
        // grosses/falsch formatiertes Logo kommt hier gar nicht erst an,
        // sondern haelt als Formularfehler auf der Seite.
        $data = $this->form->getState();

        $setting = AppSetting::query()->find(AppSetting::SINGLETON_ID);

        if (! $setting) {
            $setting = new AppSetting;
            $setting->id = AppSetting::SINGLETON_ID;
        }

        $previousLogoPath = $setting->logo_path;

        $setting->fill([
            'brand_name' => $data['brand_name'],
            'primary_color' => $data['primary_color'],
            'logo_path' => $data['logo'] ?: null,
        ]);

        $setting->save();

        // Nicht mehr referenzierte alte Logodatei aufraeumen, wenn ein neues
        // Logo hochgeladen oder das Logo entfernt wurde.
        if (filled($previousLogoPath) && $previousLogoPath !== $setting->logo_path) {
            Storage::disk('local')->delete($previousLogoPath);
        }

        // Ohne dieses Leeren wirkte die Aenderung erst nach einem Neustart -
        // siehe Kommentar an AppSetting::cached().
        AppSetting::forgetCache();

        Notification::make()
            ->title('Gespeichert')
            ->success()
            ->send();
    }
}
