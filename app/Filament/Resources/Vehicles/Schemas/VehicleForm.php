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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class VehicleForm
{
    /**
     * Nur Bildformate - ein Fahrzeugfoto ist kein Vertragsdokument. 5 MB
     * reicht fuer ein Foto in ueblicher Aufloesung deutlich.
     */
    private const ACCEPTED_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MAX_IMAGE_SIZE_KB = 5 * 1024;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fahrzeug')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Fahrzeugbild')
                            ->disk('local')
                            ->directory('vehicle-images')
                            ->acceptedFileTypes(self::ACCEPTED_IMAGE_MIME_TYPES)
                            ->maxSize(self::MAX_IMAGE_SIZE_KB)
                            // Deutsche Fehlermeldungen: Laravels eigene
                            // validation.php liegt in diesem Projekt nur auf
                            // Englisch vor (kein lang/de, kein laravel-lang-Paket),
                            // Filaments deutsche Uebersetzung deckt "mimetypes" und
                            // "max" ebenfalls nicht ab - siehe gleiches Muster bei
                            // VehicleDocumentsRelationManager.
                            ->validationMessages([
                                'mimetypes' => 'Es sind nur JPG-, PNG- und WebP-Dateien erlaubt.',
                                'max' => 'Das Bild darf höchstens 5 MB groß sein.',
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
                            })
                            ->columnSpanFull(),
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
                    ]),

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
                            ->relationship('fuelCard', 'card_number')
                            ->getOptionLabelFromRecordUsing(
                                fn ($record): string => "{$record->provider->getLabel()} {$record->card_number}"
                            )
                            ->placeholder('Auswählen')
                            ->preload(),
                    ]),

                Section::make('Vertrag')
                    ->columns(3)
                    ->schema([
                        Select::make('leasing_company_id')
                            ->label('Leasinggeber')
                            ->relationship('leasingCompany', 'name')
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
                    ]),

                Section::make('Service')
                    ->columns(2)
                    ->schema([
                        Select::make('dealership_id')
                            ->label('Autohaus')
                            ->relationship('dealership', 'name')
                            ->searchable()
                            ->placeholder('Auswählen')
                            ->preload(),
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

                Section::make('Notizen')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notizen')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
