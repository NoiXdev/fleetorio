<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Models\Vehicle;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Spiegelt die Gliederung von VehicleForm (dieselben fuenf Abschnitte in
 * derselben Reihenfolge: Fahrzeug, Zuordnung, Vertrag & Service, Kosten,
 * Notizen), damit Anzeige- und Bearbeitungsseite vertraut wirken. Der
 * Kosten-Abschnitt nutzt dieselbe Sichtbarkeitsbedingung wie dort - hier ist
 * sie sogar der einzige Schutz: eine Infolist dehydriert nichts und sendet
 * bei einer versteckten Section auch keine Werte an den Client (anders als
 * ein verstecktes Formularfeld gibt es hier keinen Zustand, der ueberhaupt
 * uebertragen werden koennte).
 */
class VehicleInfolist
{
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
     * Dieselben Abschnitte wie configure(), aber ohne "Notizen" - siehe
     * VehicleForm::mainSections() fuer die ausfuehrliche Begruendung
     * (dasselbe Problem, hier fuer ViewVehicle statt EditVehicle).
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
                    TextEntry::make('employee.full_name')
                        ->label('Mitarbeiter')
                        ->placeholder('—'),
                    // Anbieter + volle Nummer, wie im Formular (siehe
                    // VehicleForm) - dieselbe Auftraggeber-Entscheidung.
                    TextEntry::make('fuelCard.card_number')
                        ->label('Tankkarte')
                        ->state(fn (Vehicle $record): ?string => $record->fuelCard === null
                            ? null
                            : "{$record->fuelCard->fuelCardProvider->name} {$record->fuelCard->card_number}")
                        ->placeholder('—'),
                ]),

            Section::make('Vertrag & Service')
                ->columns(3)
                ->schema([
                    TextEntry::make('leasingCompany.name')
                        ->label('Leasinggeber')
                        ->placeholder('—'),
                    TextEntry::make('dealership.name')
                        ->label('Autohaus')
                        ->placeholder('—'),
                    TextEntry::make('lease_start')
                        ->label('Leasingstart')
                        ->date('d.m.Y')
                        ->placeholder('—'),
                    TextEntry::make('lease_end')
                        ->label('Leasingende')
                        ->date('d.m.Y')
                        ->placeholder('—'),
                    TextEntry::make('inspection_valid_until')
                        ->label('HU gültig bis')
                        ->date('d.m.Y')
                        ->placeholder('—'),
                ]),

            Section::make('Kosten')
                ->columns(4)
                // Derselbe Mechanismus wie in VehicleForm - siehe Kommentar
                // dort. Ein Nutzer ohne das Recht sieht die Betraege auch
                // hier nicht, auch nicht per direktem Seitenaufruf.
                ->visible(fn (): bool => auth()->user()?->canViewCosts() ?? false)
                ->schema([
                    TextEntry::make('monthly_lease_rate')
                        ->label('Leasingrate (monatlich)')
                        ->money('EUR')
                        ->placeholder('—'),
                    TextEntry::make('annual_vehicle_tax')
                        ->label('Kfz-Steuer (jährlich)')
                        ->money('EUR')
                        ->placeholder('—'),
                    TextEntry::make('annual_insurance')
                        ->label('Versicherung (jährlich)')
                        ->money('EUR')
                        ->placeholder('—'),
                    TextEntry::make('gross_list_price')
                        ->label('Bruttolistenpreis')
                        ->money('EUR')
                        ->placeholder('—'),
                    // Abgeleiteter Wert (siehe Vehicle::annualTotalCost()) -
                    // die Spezifikation verlangt ausgewiesene Jahreskosten
                    // direkt am Fahrzeug, nicht nur auf der Kostenseite.
                    TextEntry::make('annual_total_cost')
                        ->label('Jahreskosten')
                        ->money('EUR'),
                ]),
        ];
    }

    /**
     * Eigener Abschnitt statt Teil von mainSections(): siehe Kommentar an
     * mainSections() - ViewVehicle bettet ihn separat und erst nach den
     * Relationsmanagern ein.
     */
    public static function notesSection(): Component
    {
        return Section::make('Notizen')
            ->schema([
                TextEntry::make('notes')
                    ->label('Notizen')
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Einzige Ausnahme von der sonst durchgehend einspaltigen Gliederung
     * (Auftraggeber-Wunsch): links das Bild, rechts die Stammdaten, im
     * Verhaeltnis 30/70 - identisch zu VehicleForm::vehicleSection().
     */
    private static function vehicleSection(): Component
    {
        return Section::make('Fahrzeug')
            ->columns(['default' => 1, 'lg' => 10])
            // Bild vergroessert ansehen: eine Filament-Action statt einer
            // externen Lightbox-Bibliothek (die Anwendung laedt bewusst ohne
            // Verbindung nach draussen, siehe Spezifikation). Das Bild kommt
            // im Modal ueber dieselbe autorisierte Route wie das Vorschaubild
            // selbst (vendor/filament/actions/src/Concerns/CanOpenModal.php,
            // modalContent() akzeptiert ein Htmlable - Blade {{ }} gibt ein
            // Htmlable unveraendert zurueck, siehe Illuminate\Support\e()).
            ->headerActions([
                Action::make('viewImage')
                    ->label('Bild vergrößern')
                    ->icon(Heroicon::OutlinedArrowsPointingOut)
                    ->visible(fn (Vehicle $record): bool => filled($record->image_path))
                    ->modalHeading('Fahrzeugbild')
                    ->modalWidth(Width::FourExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Schließen')
                    ->modalContent(fn (Vehicle $record): HtmlString => new HtmlString(
                        '<img src="'.e(route('vehicle-images.show', $record)).'" alt="Fahrzeugbild" class="w-full h-auto rounded-lg">'
                    )),
            ])
            ->schema([
                Group::make([
                    ImageEntry::make('image_path')
                        ->label('Fahrzeugbild')
                        ->hiddenLabel()
                        // ImageEntry::getImageUrl() prueft zuerst per
                        // filter_var(..., FILTER_VALIDATE_URL), ob der Zustand
                        // bereits eine vollstaendige URL ist, und liefert sie
                        // in diesem Fall direkt zurueck - ohne den
                        // konfigurierten Disk ueberhaupt anzufassen (siehe
                        // vendor/filament/infolists/src/Components/
                        // ImageEntry.php, getImageUrl()). Genau das nutzt
                        // ->state() hier aus: statt des rohen Pfads liefert es
                        // die URL des autorisierten Controllers, sodass
                        // Storage::url()/temporaryUrl() (fuer den privaten
                        // 'local'-Disk ohnehin ungeeignet, siehe Spezifikation)
                        // gar nicht erst zum Zug kommen.
                        ->state(fn (Vehicle $record): ?string => filled($record->image_path)
                            ? route('vehicle-images.show', $record)
                            : null)
                        ->square()
                        ->imageHeight('12rem')
                        ->placeholder('Kein Bild hinterlegt'),
                ])->columnSpan(['default' => 1, 'lg' => 3]),

                Group::make([
                    TextEntry::make('brand')
                        ->label('Marke'),
                    TextEntry::make('model')
                        ->label('Modell'),
                    TextEntry::make('license_plate')
                        ->label('Kennzeichen'),
                    TextEntry::make('vin')
                        ->label('Fahrgestellnummer (VIN)')
                        ->placeholder('—'),
                    TextEntry::make('fuel_type')
                        ->label('Antriebsart')
                        ->badge(),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge(),
                ])
                    ->columns(2)
                    ->columnSpan(['default' => 1, 'lg' => 7]),
            ]);
    }
}
