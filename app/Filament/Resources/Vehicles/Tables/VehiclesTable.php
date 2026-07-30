<?php

namespace App\Filament\Resources\Vehicles\Tables;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use App\Filament\Exports\VehicleExporter;
use App\Models\Vehicle;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Bild')
                    // ImageColumn::getImageUrl() liefert einen bereits als URL
                    // erkannten Zustand direkt zurueck (filter_var(...,
                    // FILTER_VALIDATE_URL), siehe vendor/filament/tables/src/
                    // Columns/ImageColumn.php) - ohne den konfigurierten Disk
                    // ueberhaupt anzufassen. Genau das nutzt ->state() hier
                    // aus, wie bei ImageEntry in VehicleInfolist: statt des
                    // rohen Pfads liefert es die URL des autorisierten
                    // Controllers.
                    ->state(fn (Vehicle $record): ?string => filled($record->image_path)
                        ? route('vehicle-images.show', $record)
                        : null)
                    ->square()
                    ->imageSize(40)
                    ->placeholder('—'),
                TextColumn::make('license_plate')
                    ->label('Kennzeichen')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand')
                    ->label('Marke')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('model')
                    ->label('Modell')
                    ->searchable(),
                TextColumn::make('employee.last_name')
                    ->label('Fahrer')
                    ->formatStateUsing(fn ($state, Vehicle $record): string => $record->employee?->full_name ?? '—')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('fuel_type')
                    ->label('Antriebsart')
                    ->badge()
                    ->sortable(),
                TextColumn::make('lease_end')
                    ->label('Leasingende')
                    ->date('d.m.Y')
                    ->badge()
                    // Ohne Leasingende (seit diesem Wunsch moeglich) gibt es weder ein
                    // Datum noch eine Dringlichkeit anzuzeigen. Filament rendert eine
                    // leere Spalte ueber den Placeholder-Zweig, *bevor* formatState()
                    // oder die color()-Closure ueberhaupt aufgerufen werden (siehe
                    // vendor/filament/tables/src/Columns/TextColumn.php,
                    // toEmbeddedHtml(), der blank($state)-Zweig) - der Platzhalter
                    // erscheint also automatisch ohne Farbmarkierung.
                    ->placeholder('—')
                    ->color(fn (Vehicle $record): ?string => $record->lease_end === null
                        ? null
                        : Vehicle::leaseUrgencyColor((int) today()->diffInDays($record->lease_end, false)))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('lease_end')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(VehicleStatus::class)
                    // Ausgemusterte Fahrzeuge sind zuschaltbar, aber nicht Standard.
                    ->default(VehicleStatus::Active->value),
                SelectFilter::make('fuel_type')
                    ->label('Antriebsart')
                    ->options(FuelType::class),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export')
                    ->exporter(VehicleExporter::class),
            ])
            // Bewusst kein Mehrfachlöschen: Fahrzeuge werden ausgemustert, nicht
            // gelöscht. Mit zwei Klicks den halben Fuhrpark samt Kostenhistorie zu
            // verlieren, ist ein zu großes Risiko für einen Bequemlichkeitsgewinn.
            // Einzelnes Löschen bleibt auf der Edit-Seite verfügbar (nur Admins,
            // siehe VehiclePolicy::delete).
            //
            // Der Klick auf eine Zeile fuehrt bereits automatisch zur
            // Anzeigeseite: Filament setzt recordUrl() selbst, sobald die
            // Ressource eine 'view'-Seite registriert (siehe Filament\
            // Resources\Pages\ListRecords::table(), der Block
            // "if (! $table->hasCustomRecordUrl())"), kein eigenes
            // ->recordUrl() noetig. Die ViewAction unten ist der zusaetzliche
            // Weg ueber das Aktionsmenue.
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
