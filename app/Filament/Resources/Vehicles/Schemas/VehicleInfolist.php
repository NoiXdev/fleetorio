<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Models\Vehicle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Spiegelt die Gliederung von VehicleForm (dieselben fuenf Abschnitte in
 * derselben Reihenfolge), damit Anzeige- und Bearbeitungsseite vertraut
 * wirken. Der Kosten-Abschnitt nutzt dieselbe Sichtbarkeitsbedingung wie
 * dort - hier ist sie sogar der einzige Schutz: eine Infolist dehydriert
 * nichts und sendet bei einer versteckten Section auch keine Werte an den
 * Client (anders als ein verstecktes Formularfeld gibt es hier keinen
 * Zustand, der ueberhaupt uebertragen werden koennte).
 */
class VehicleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fahrzeug')
                    ->columns(2)
                    ->schema([
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
                            ->imageHeight('8rem')
                            ->placeholder('Kein Bild hinterlegt')
                            ->columnSpanFull(),
                        TextEntry::make('brand')
                            ->label('Marke'),
                        TextEntry::make('model')
                            ->label('Modell'),
                        TextEntry::make('license_plate')
                            ->label('Kennzeichen'),
                        TextEntry::make('fuel_type')
                            ->label('Antriebsart')
                            ->badge(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                    ]),

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
                                : "{$record->fuelCard->provider->getLabel()} {$record->fuelCard->card_number}")
                            ->placeholder('—'),
                    ]),

                Section::make('Vertrag')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('leasingCompany.name')
                            ->label('Leasinggeber')
                            ->placeholder('—'),
                        TextEntry::make('lease_start')
                            ->label('Leasingstart')
                            ->date('d.m.Y')
                            ->placeholder('—'),
                        TextEntry::make('lease_end')
                            ->label('Leasingende')
                            ->date('d.m.Y')
                            ->placeholder('—'),
                    ]),

                Section::make('Service')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('dealership.name')
                            ->label('Autohaus')
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
                        // Abgeleiteter Wert (siehe Vehicle::annualTotalCost()) -
                        // die Spezifikation verlangt ausgewiesene Jahreskosten
                        // direkt am Fahrzeug, nicht nur auf der Kostenseite.
                        TextEntry::make('annual_total_cost')
                            ->label('Jahreskosten')
                            ->money('EUR'),
                    ]),

                Section::make('Notizen')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notizen')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
