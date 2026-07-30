<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use App\Enums\DocumentType;
use App\Models\VehicleDocument;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Anlegen/Bearbeiten/Loeschen sind ueber App\Policies\VehicleDocumentPolicy nur
 * Administratoren vorbehalten - Filament prueft das automatisch anhand der
 * Standard-Actions unten, siehe RelationManager::getDefaultActionAuthorizationResponse()
 * (gleiches Muster wie VehicleAppointmentsRelationManager). Lesen/Herunterladen
 * bleibt jedem angemeldeten Benutzer erlaubt.
 *
 * Der eigentliche Zugriffsschutz auf die Datei selbst liegt NICHT hier,
 * sondern im Download-Controller (App\Http\Controllers\
 * VehicleDocumentDownloadController) - die "Herunterladen"-Aktion unten
 * verlinkt nur dorthin.
 */
class VehicleDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Dokumente';

    /**
     * RelationManager ist per CanBeLazy (Filament\Support\Concerns\CanBeLazy,
     * $isLazy-Property) standardmaessig lazy: der Livewire-Kind wird erst per
     * Intersection-Observer (x-intersect) nachgeladen. Das war bislang
     * unauffaellig, weil nur der aktive Tab ueberhaupt sichtbar war. Seit
     * Dokumente und Termine untereinander stehen (siehe
     * VehicleResource::getRelations(), RelationGroup statt zwei einzelner
     * Eintraege), sollen beide Listen sofort mitsamt Inhalt erscheinen, ohne
     * auf das Einblenden im Viewport zu warten.
     */
    protected static bool $isLazy = false;

    /**
     * Erlaubte Dateitypen und Groessenobergrenze fuer Fahrzeugdokumente
     * (Fahrzeugschein, Leasingvertrag, Uebergabeprotokoll, HU-Bericht,
     * Versicherungsschein, Sonstiges): PDF fuer Vertragsdokumente und
     * Berichte, JPG/PNG fuer abfotografierte Papierdokumente - mehr wird in
     * der Praxis nicht gebraucht und jeder weitere Dateityp waere ein
     * unnoetig groesseres Angriffsfenster (z.B. ausfuehrbarer Code, aktive
     * Formate wie SVG/HTML). 10 MB reicht fuer gescannte oder fotografierte
     * Dokumente in ueblicher Aufloesung deutlich und begrenzt gleichzeitig
     * den Schaden durch versehentliche oder mutwillige Grossuploads.
     */
    private const ACCEPTED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    private const MAX_FILE_SIZE_KB = 25 * 1024;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('file_path')
                    ->label('Datei')
                    ->disk('local')
                    ->directory('vehicle-documents')
                    ->acceptedFileTypes(self::ACCEPTED_MIME_TYPES)
                    ->maxSize(self::MAX_FILE_SIZE_KB)
                    // Deutsche Fehlermeldungen: Laravels eigene
                    // validation.php liegt in diesem Projekt nur auf
                    // Englisch vor (kein lang/de, kein laravel-lang-Paket),
                    // Filaments deutsche Uebersetzung deckt "mimetypes" und
                    // "max" ebenfalls nicht ab - ohne diese beiden Zeilen
                    // erschienen die Meldungen auf Englisch, obwohl der Rest
                    // der Oberflaeche deutsch ist.
                    ->validationMessages([
                        'mimetypes' => 'Es sind nur PDF-, JPG- und PNG-Dateien erlaubt.',
                        // Zahl aus der Konstante ableiten, nicht in den Text
                        // schreiben: sonst nennt die Meldung nach einer
                        // Aenderung der Grenze stillschweigend den falschen Wert.
                        'max' => 'Die Datei darf höchstens '.(self::MAX_FILE_SIZE_KB / 1024).' MB groß sein.',
                    ])
                    // Nur beim Anlegen sichtbar/erforderlich: ein Austausch
                    // der Datei auf einem bestehenden Dokument ist nicht
                    // Teil dieser Aufgabe (Gefahr verwaister Altdateien ohne
                    // eigene Aufraeumlogik dafuer). Bearbeiten aendert nur
                    // Art, Bezeichnung und Notiz.
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    // Erfasst die Originalmerkmale der Datei, bevor
                    // saveUploadedFiles() (beforeStateDehydrated) daraus nur
                    // noch den zufaelligen Speicherpfad macht - siehe
                    // BaseFileUpload::setUp(). Der Speicherpfad selbst
                    // bleibt zufaellig (Filaments Standardverhalten, siehe
                    // getUploadedFileNameForStorageUsing()), der
                    // urspruengliche Name steht separat in der Datenbank.
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        if (! $state instanceof TemporaryUploadedFile) {
                            return;
                        }

                        $set('original_filename', $state->getClientOriginalName());
                        $set('file_size', $state->getSize());
                        // getMimeType() statt getClientMimeType(): erkennt den Typ
                        // aus dem tatsaechlichen Dateiinhalt (Fileinfo) statt dem vom
                        // Client behaupteten Header - der Client koennte diesen
                        // Header beliebig faelschen.
                        $set('mime_type', $state->getMimeType());
                    }),
                Hidden::make('original_filename'),
                Hidden::make('file_size'),
                Hidden::make('mime_type'),
                Select::make('type')
                    ->label('Art')
                    ->options(DocumentType::class)
                    ->native(false)
                    ->placeholder('Auswählen')
                    ->required(),
                TextInput::make('title')
                    ->label('Bezeichnung')
                    ->required()
                    ->maxLength(255),
                Textarea::make('notes')
                    ->label('Notiz')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label('Art')
                    ->badge(),
                TextColumn::make('title')
                    ->label('Bezeichnung'),
                TextColumn::make('original_filename')
                    ->label('Dateiname'),
                TextColumn::make('file_size')
                    ->label('Größe')
                    ->formatStateUsing(fn (VehicleDocument $record): string => $record->formattedFileSize()),
                TextColumn::make('created_at')
                    ->label('Datum')
                    ->date('d.m.Y'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Herunterladen')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (VehicleDocument $record): string => route('vehicle-documents.download', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
