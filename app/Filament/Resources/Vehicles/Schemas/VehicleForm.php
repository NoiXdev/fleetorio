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
                        // Inaktive gehoeren nicht in eine Neuzuordnung, der
                        // bereits gesetzte Wert MUSS aber in der Liste bleiben:
                        // sonst faende das Formular den gespeicherten Wert
                        // nicht wieder und wuerde die Zuordnung beim naechsten
                        // Speichern still leeren. Dasselbe Muster in allen vier
                        // Selects dieser Section und der Vertrag-Section.
                        ->relationship(
                            name: 'employee',
                            titleAttribute: 'last_name',
                            modifyQueryUsing: fn ($query, ?Vehicle $record) => $query
                                ->where(fn ($q) => $q->where('active', true)
                                    ->orWhere('id', $record?->employee_id)),
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record): string => $record->full_name
                            .($record->active ? '' : ' (inaktiv)'))
                        ->searchable(['first_name', 'last_name'])
                        ->placeholder('Auswählen')
                        ->preload(),
                    Select::make('fuel_card_id')
                        ->label('Tankkarte')
                        // Anbieter + volle Nummer (Auftraggeber-Entscheidung),
                        // damit man bei mehreren Anbietern nicht raten muss.
                        // Der encrypted-Cast schuetzt weiterhin die Datenbank.
                        ->relationship(
                            name: 'fuelCard',
                            titleAttribute: 'card_number',
                            modifyQueryUsing: fn ($query, ?Vehicle $record) => $query
                                ->with('fuelCardProvider')
                                ->where(fn ($q) => $q->where('active', true)
                                    ->orWhere('id', $record?->fuel_card_id)),
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn ($record): string => "{$record->fuelCardProvider->name} {$record->card_number}"
                                .($record->active ? '' : ' (inaktiv)')
                        )
                        ->placeholder('Auswählen')
                        ->preload(),
                ]),

            Section::make('Vertrag & Service')
                ->columns(3)
                ->schema([
                    Select::make('leasing_company_id')
                        ->label('Leasinggeber')
                        ->relationship(
                            name: 'leasingCompany',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn ($query, ?Vehicle $record) => $query
                                ->where(fn ($q) => $q->where('active', true)
                                    ->orWhere('id', $record?->leasing_company_id)),
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record): string => $record->name
                            .($record->active ? '' : ' (inaktiv)'))
                        ->searchable()
                        ->placeholder('Auswählen')
                        ->preload(),
                    Select::make('dealership_id')
                        ->label('Autohaus')
                        ->relationship(
                            name: 'dealership',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn ($query, ?Vehicle $record) => $query
                                ->where(fn ($q) => $q->where('active', true)
                                    ->orWhere('id', $record?->dealership_id)),
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record): string => $record->name
                            .($record->active ? '' : ' (inaktiv)'))
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
                    TextInput::make('gross_list_price')
                        ->label('Bruttolistenpreis')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('€')
                        ->helperText('Listenpreis bei Erstzulassung. Fließt nicht in die Jahreskosten ein.')
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
                        // Zuschnitt fest auf 4:3 (Auftraggeber-Wunsch). Der
                        // eingebaute Bildeditor (Cropper.js, Teil von Filaments
                        // eigenem JS-Bundle, kein CDN) oeffnet sich automatisch,
                        // sobald ein hochgeladenes Bild nicht bereits 4:3 ist -
                        // siehe automaticallyOpenImageEditorForAspectRatio() in
                        // vendor/filament/forms/docs/09-file-upload.md,
                        // Abschnitt "Enforcing a specific aspect ratio". Da hier
                        // nur ein einziges Seitenverhaeltnis uebergeben wird
                        // (kein imageEditorAspectRatioOptions() mit Alternativen),
                        // ist 4:3 die einzig waehlbare Option - der Cropper
                        // erzwingt sie ueber cropperOptions.aspectRatio (siehe
                        // file-upload.js, initEditor()).
                        //
                        // imageAspectRatio() ist zugleich eine echte
                        // serverseitige Pruefung (Rule::dimensions()->ratio(),
                        // siehe BaseFileUpload::getRules()) - falls ein
                        // Bild ohne Browser-JS eingereicht wuerde, weist der
                        // Server es dennoch zurueck. getimagesize() dahinter
                        // ist eine PHP-Kernfunktion, keine GD/Imagick-Pflicht.
                        ->imageEditor()
                        ->imageAspectRatio('4:3')
                        ->automaticallyOpenImageEditorForAspectRatio()
                        // Verkleinerung auf 2000 Pixel Breite: das laeuft
                        // vollstaendig im Browser (FilePondPluginImageResize,
                        // Canvas-basiert), BEVOR die Datei ueberhaupt hochgeladen
                        // wird - der Server bekommt bereits die verkleinerte
                        // Datei und veraendert sie nicht weiter. Das erfordert
                        // keine PHP-Bildbibliothek (weder GD noch Imagick); siehe
                        // Bericht fuer die vollstaendige Herleitung. Ein Client
                        // ohne JavaScript (oder ein direkter API-Aufruf ohne
                        // Browser) haelt sich nicht an diese Grenze - anders als
                        // beim Seitenverhaeltnis oben gibt es dafuer keine
                        // serverseitige Zweitpruefung, da Filament dafuer keine
                        // eingebaute Validierungsregel mitbringt.
                        ->automaticallyResizeImagesMode('cover')
                        ->automaticallyResizeImagesToWidth('2000')
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
                            'dimensions' => 'Das Bild muss im Seitenverhältnis 4:3 zugeschnitten sein.',
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
                        ->unique(ignoreRecord: true)
                        // Ein ausgemustertes Fahrzeug belegt sein Kennzeichen
                        // weiterhin (so gewollt - sonst stuende dasselbe
                        // Kennzeichen zweimal in der Tabelle). Ohne diesen
                        // Hinweis sucht man in der Liste nach einem Eintrag,
                        // der dort standardmaessig nicht sichtbar ist.
                        ->validationMessages([
                            'unique' => 'Dieses Kennzeichen ist bereits vergeben — '
                                .'möglicherweise von einem ausgemusterten Fahrzeug. '
                                .'Über den Statusfilter in der Fahrzeugliste sind diese sichtbar.',
                        ]),
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
