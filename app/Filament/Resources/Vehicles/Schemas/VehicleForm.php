<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Reihenfolge und Gliederung sind Auftraggeber-Wunsch (siehe Spezifikation):
 * Fahrzeug (als einzige Ausnahme zweispaltig, 30/70 Bild/Stammdaten),
 * darunter Zuordnung, Vertrag & Service (zusammengelegt), Kosten, Notizen -
 * jeweils ueber die volle Breite. VehicleInfolist spiegelt dieselbe
 * Gliederung fuer die Anzeigeseite.
 */
class VehicleForm
{
    /**
     * Nur Bildformate - ein Fahrzeugfoto ist kein Vertragsdokument. 5 MB
     * reicht fuer ein Foto in ueblicher Aufloesung deutlich.
     */
    private const ACCEPTED_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MAX_IMAGE_SIZE_KB = 10 * 1024;

    /**
     * 17 Zeichen, ohne I, O und Q (ISO 3779) - diese drei Buchstaben duerfen
     * in einer VIN nicht vorkommen, damit sie nicht mit 1 bzw. 0 verwechselt
     * werden. Zentral hier, weil sowohl das Formular als auch dessen Test
     * dieselbe Regel brauchen.
     */
    public const VIN_REGEX = '/^[A-HJ-NPR-Z0-9]{17}$/';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Volle Breite (Wunsch des Auftraggebers). columnSpanFull() ist
                // dabei entscheidend: ohne den Aufruf belegt das Grid nur eine
                // Spalte des uebergeordneten Layouts und die Abschnitte enden
                // bei etwa der halben Fensterbreite.
                Grid::make(1)
                    ->columnSpanFull()
                    ->schema([
                        ...static::mainSections(),
                        static::notesSection(),
                    ]),
            ]);
    }

    /**
     * Dieselben Abschnitte wie configure(), aber ohne "Notizen". Wird von
     * EditVehicle gebraucht: dort sollen die Dokumente/Termine-
     * Relationsmanager zwischen "Kosten" und "Notizen" erscheinen (Wunsch des
     * Auftraggebers), Filament rendert Relationsmanager aber grundsaetzlich
     * erst NACH der eingebetteten 'form'-Schema (siehe EditRecord::content()
     * bzw. getRelationManagersContentComponent()) - ein einzelner
     * EmbeddedSchema-Block laesst sich nicht von innen aufteilen. EditVehicle
     * registriert "Notizen" deshalb als eigene, zweite Schema ('notesForm',
     * siehe dort) und embedded sie erst nach den Relationsmanagern.
     *
     * @return array<Component>
     */
    public static function mainSections(): array
    {
        return [
            static::vehicleSection(),

            Section::make('Zuordnung')
                ->columns(2)
                ->schema([
                    Select::make('employee_id')
                        ->label('Mitarbeiter')
                        ->relationship('employee', 'last_name')
                        ->getOptionLabelFromRecordUsing(fn ($record): string => $record->full_name)
                        ->searchable(['first_name', 'last_name'])
                        ->placeholder('Auswählen')
                        ->preload(),
                    Select::make('fuel_card_id')
                        ->label('Tankkarte')
                        // Anbieter + volle Nummer (Auftraggeber-Entscheidung),
                        // damit man bei mehreren Anbietern nicht raten muss.
                        // Der encrypted-Cast schuetzt weiterhin die Datenbank.
                        ->relationship('fuelCard', 'card_number', fn ($query) => $query->with('fuelCardProvider'))
                        ->getOptionLabelFromRecordUsing(
                            fn ($record): string => "{$record->fuelCardProvider->name} {$record->card_number}"
                        )
                        ->placeholder('Auswählen')
                        ->preload(),
                ]),

            Section::make('Vertrag & Service')
                ->columns(3)
                ->schema([
                    Select::make('leasing_company_id')
                        ->label('Leasinggeber')
                        ->relationship('leasingCompany', 'name')
                        ->searchable()
                        ->placeholder('Auswählen')
                        ->preload(),
                    Select::make('dealership_id')
                        ->label('Autohaus')
                        ->relationship('dealership', 'name')
                        ->searchable()
                        ->placeholder('Auswählen')
                        ->preload(),
                    // Native statt Filaments eigenem Picker: Der eigene Picker
                    // rendert das sichtbare Feld als readonly-Input in einem
                    // Button (vendor/filament/forms/src/Components/
                    // DateTimePicker.php) - eine Tastatureingabe ist dort nicht
                    // moeglich. Das native Feld ist es und zeigt im deutschen
                    // Browser TT.MM.JJJJ; ->displayFormat() wirkt bei ihm nicht
                    // und entfaellt daher.
                    DatePicker::make('lease_start')
                        ->label('Leasingstart'),
                    DatePicker::make('lease_end')
                        ->label('Leasingende')
                        ->after('lease_start'),
                    DatePicker::make('inspection_valid_until')
                        ->label('HU gültig bis'),
                ]),

            Section::make('Kosten')
                ->columns(3)
                // Der eigentliche Schutz vor Datenverlust entsteht bereits hier:
                // Wenn diese Section versteckt ist, meldet ihr isDehydrated()
                // false (Filament dehydriert eine ausgeblendete Komponente nur,
                // wenn dehydratedWhenHidden() explizit gesetzt ist), und dieser
                // Zustand wird an alle Kind-Komponenten weitergereicht. Beim
                // Aufbau des gespeicherten Formularzustands werden deren
                // Statuspfade dadurch verworfen (Arr::forget), bevor das
                // dehydrated()-Flag der einzelnen Felder unten überhaupt
                // ausgewertet wird. Siehe vendor/filament/schemas/src/
                // Components/Concerns/HasState.php, dehydrateState()
                // (Zeilen 316-356) und isHiddenAndNotDehydratedWhenHidden().
                ->visible(fn (): bool => auth()->user()?->canViewCosts() ?? false)
                ->schema([
                    TextInput::make('monthly_lease_rate')
                        ->label('Leasingrate (monatlich)')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('€')
                        // Verteidigung in der Tiefe: greift nur, falls dieses
                        // Feld je aus der Section herausgelöst und nur noch
                        // feldweise versteckt wird. Der eigentliche Schutz
                        // (siehe Kommentar an der Section oben) wirkt bereits
                        // vorher.
                        ->dehydrated(fn (): bool => auth()->user()?->canViewCosts() ?? false),
                    TextInput::make('annual_vehicle_tax')
                        ->label('Kfz-Steuer (jährlich)')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('€')
                        ->dehydrated(fn (): bool => auth()->user()?->canViewCosts() ?? false),
                    TextInput::make('annual_insurance')
                        ->label('Versicherung (jährlich)')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('€')
                        ->dehydrated(fn (): bool => auth()->user()?->canViewCosts() ?? false),
                ]),
        ];
    }

    /**
     * Eigener Abschnitt statt Teil von mainSections(): siehe Kommentar an
     * mainSections() - EditVehicle bettet ihn separat und erst nach den
     * Relationsmanagern ein.
     */
    public static function notesSection(): Component
    {
        return Section::make('Notizen')
            ->schema([
                Textarea::make('notes')
                    ->label('Notizen')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Einzige Ausnahme von der sonst durchgehend einspaltigen Gliederung
     * (Auftraggeber-Wunsch): links das Bild, rechts die Stammdaten, im
     * Verhaeltnis 30/70. Auf schmalen Bildschirmen (Tablet, kein 'lg')
     * faellt das auf eine Spalte zurueck - erst das Bild, dann die Felder.
     */
    private static function vehicleSection(): Component
    {
        return Section::make('Fahrzeug')
            ->columns(['default' => 1, 'lg' => 10])
            ->schema([
                Group::make([
                    FileUpload::make('image_path')
                        ->label('Fahrzeugbild')
                        ->disk('local')
                        ->directory('vehicle-images')
                        ->acceptedFileTypes(self::ACCEPTED_IMAGE_MIME_TYPES)
                        ->maxSize(self::MAX_IMAGE_SIZE_KB)
                        // Der Zuschneide-Editor (Cropper.js, Teil von Filaments
                        // eigenem JS-Bundle, kein CDN) bleibt verfuegbar, aber
                        // freiwillig: kein festes Seitenverhaeltnis mehr.
                        //
                        // Frueher stand hier imageAspectRatio('4:3') zusammen mit
                        // automaticallyOpenImageEditorForAspectRatio(). Das war
                        // nicht nur eine Vorgabe fuer den Editor, sondern
                        // erzeugte serverseitig Rule::dimensions()->ratio()
                        // (siehe BaseFileUpload::getRules()). Jedes Bild in einem
                        // anderen Verhaeltnis wurde damit abgewiesen - laut
                        // Anforderung soll die Bildgroesse aber egal sein, es
                        // gilt nur noch maxSize() weiter unten.
                        ->imageEditor()
                        // Verkleinerung auf 2000 Pixel Breite: das laeuft
                        // vollstaendig im Browser (FilePondPluginImageResize,
                        // Canvas-basiert), BEVOR die Datei ueberhaupt hochgeladen
                        // wird - der Server bekommt bereits die verkleinerte
                        // Datei und veraendert sie nicht weiter. Das erfordert
                        // keine PHP-Bildbibliothek (weder GD noch Imagick). Ein
                        // Client ohne JavaScript (oder ein direkter API-Aufruf
                        // ohne Browser) haelt sich nicht an diese Grenze; eine
                        // serverseitige Zweitpruefung dafuer bringt Filament
                        // nicht mit. Die Grenze spart Speicher, sie schuetzt
                        // nichts - das tut maxSize().
                        //
                        // 'contain' statt 'cover': ohne festes Seitenverhaeltnis
                        // und ohne gesetzte Zielhoehe wuerde 'cover' ein Panorama
                        // auf ein Quadrat beschneiden. 'contain' skaliert nur
                        // herunter und schneidet nie.
                        //
                        // upscale(false) ist der zweite Teil des behobenen
                        // Fehlers: Filaments Standard ist true, wodurch ein Bild
                        // mit 800 Pixel Breite auf 2000 hochgerechnet wurde -
                        // sichtbar unscharf und um ein Vielfaches groesser als
                        // das Original.
                        ->automaticallyResizeImagesMode('contain')
                        ->automaticallyResizeImagesToWidth('2000')
                        ->automaticallyUpscaleImagesWhenResizing(false)
                        // Deutsche Fehlermeldungen: Laravels eigene
                        // validation.php liegt in diesem Projekt nur auf
                        // Englisch vor (kein lang/de, kein laravel-lang-Paket),
                        // Filaments deutsche Uebersetzung deckt "mimetypes",
                        // "max" und "dimensions" ebenfalls nicht ab - siehe
                        // gleiches Muster bei VehicleDocumentsRelationManager.
                        ->validationMessages([
                            'mimetypes' => 'Es sind nur JPG-, PNG- und WebP-Dateien erlaubt.',
                            // Zahl aus der Konstante, siehe Kommentar im
                            // VehicleDocumentsRelationManager.
                            'max' => 'Das Bild darf höchstens '.(self::MAX_IMAGE_SIZE_KB / 1024).' MB groß sein.',
                        ])
                        ->imagePreviewHeight('160')
                        // Ohne diese Ueberschreibung wuerde die Vorschau eines
                        // bereits gespeicherten Bildes auf ein kaputtes
                        // <img>-Tag zeigen: BaseFileUpload::getUploadedFile()
                        // versucht fuer ein privates Disk zuerst
                        // Storage::temporaryUrl() (der 'local'-Treiber
                        // unterstuetzt das nicht, siehe
                        // FilesystemAdapter::providesTemporaryUrls()) und
                        // faellt danach auf Storage::url() zurueck, was ohne
                        // symbolischen Link auf /storage/... zeigt - eine nicht
                        // erreichbare Adresse. Der autorisierte Controller ist
                        // hier derselbe wie fuer ImageEntry/ImageColumn, siehe
                        // VehicleInfolist bzw. VehiclesTable.
                        ->getUploadedFileUsing(function (string $file, ?Vehicle $record): ?array {
                            if ($record === null) {
                                return null;
                            }

                            return [
                                'name' => basename($file),
                                'size' => Storage::disk('local')->exists($file)
                                    ? Storage::disk('local')->size($file)
                                    : 0,
                                'type' => null,
                                'url' => route('vehicle-images.show', $record),
                            ];
                        }),
                ])->columnSpan(['default' => 1, 'lg' => 3]),

                Group::make([
                    TextInput::make('brand')
                        ->label('Marke')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('model')
                        ->label('Modell')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('license_plate')
                        ->label('Kennzeichen')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    // Nicht Pflicht (Auftraggeber-Entscheidung): bei Altbestand
                    // ist die Fahrgestellnummer nicht immer erfasst. Die
                    // Umwandlung in Grossbuchstaben passiert VOR der
                    // Validierung (afterStateUpdated feuert bei jeder
                    // Zustandsaenderung, auch in Tests ueber fillForm() - noch
                    // bevor save() den Formularzustand validiert), damit eine
                    // klein geschriebene, sonst gueltige VIN nicht an der
                    // Gross-Regex unten scheitert.
                    TextInput::make('vin')
                        ->label('Fahrgestellnummer (VIN)')
                        ->maxLength(17)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
                            'vin',
                            filled($state) ? strtoupper($state) : $state,
                        ))
                        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper($state) : $state)
                        ->regex(self::VIN_REGEX)
                        ->validationMessages([
                            'regex' => 'Die Fahrgestellnummer muss aus 17 Zeichen bestehen und darf die Buchstaben I, O oder Q nicht enthalten.',
                        ]),
                    Select::make('fuel_type')
                        ->label('Antriebsart')
                        ->options(FuelType::class)
                        ->native(false)
                        // Kein Standardwert - ohne eigenen Platzhalter würde
                        // Filaments deutsche Übersetzung "Wählen Sie eine
                        // Option" zeigen (vendor/filament/forms/resources/
                        // lang/de/components.php, select.placeholder).
                        ->placeholder('Auswählen')
                        ->required(),
                    Select::make('status')
                        ->label('Status')
                        ->options(VehicleStatus::class)
                        ->native(false)
                        // Hat einen Standardwert - ein Platzhalter würde hier
                        // nie angezeigt, daher bewusst kein ->placeholder().
                        ->default(VehicleStatus::Active->value)
                        ->required(),
                ])
                    ->columns(2)
                    ->columnSpan(['default' => 1, 'lg' => 7]),
            ]);
    }
}
