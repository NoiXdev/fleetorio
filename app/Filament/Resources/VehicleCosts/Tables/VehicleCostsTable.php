<?php

namespace App\Filament\Resources\VehicleCosts\Tables;

use App\Enums\VehicleStatus;
use App\Filament\Exports\VehicleCostExporter;
use App\Models\Vehicle;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;

class VehicleCostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('license_plate')
                    ->label('Kennzeichen')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('monthly_lease_rate')
                    ->label('Leasingrate (mtl.)')
                    ->money('EUR')
                    ->sortable()
                    // Eigene ID statt numerischer Autoindizierung, damit
                    // assertTableColumnSummarySet() in Tests einen sprechenden
                    // Schlüssel statt eines kryptischen '0' ansprechen kann.
                    ->summarize(Sum::make('lease_sum')->label('Summe')->money('EUR')),
                TextColumn::make('annual_vehicle_tax')
                    ->label('Kfz-Steuer (jährl.)')
                    ->money('EUR')
                    ->sortable()
                    ->summarize(Sum::make('tax_sum')->label('Summe')->money('EUR')),
                TextColumn::make('annual_insurance')
                    ->label('Versicherung (jährl.)')
                    ->money('EUR')
                    ->sortable()
                    ->summarize(Sum::make('insurance_sum')->label('Summe')->money('EUR')),
                // Bewusst OHNE Summarizer und OHNE Anteil an annual_total_cost:
                // Ein Listenpreis ist keine laufende Position, die man ueber
                // einen Fuhrpark aufsummiert oder in Jahreskosten einrechnet.
                TextColumn::make('gross_list_price')
                    ->label('Bruttolistenpreis')
                    ->money('EUR')
                    ->sortable()
                    ->placeholder('—'),
                // Abgeleiteter Wert: nicht sortierbar, da keine Datenbankspalte.
                TextColumn::make('annual_total_cost')
                    ->label('Jahreskosten')
                    ->money('EUR')
                    ->summarize(
                        Summarizer::make('total_sum')
                            ->label('Summe')
                            ->money('EUR')
                            // Filament liefert hier einen Illuminate\Database\Query\Builder
                            // (intern per ->toBase() erzeugt), keinen Eloquent-Builder wie im
                            // Task-Brief angenommen. sumAnnualCost() verlangt bewusst einen
                            // Eloquent-Builder (siehe VehicleCostSumTest), daher hier zurueck-
                            // wickeln statt die Signatur der Modell-Methode aufzuweichen.
                            ->using(fn (Builder $query): float => Vehicle::sumAnnualCost(
                                Vehicle::query()->setQuery($query)
                            ))
                    ),
            ])
            ->defaultSort('license_plate')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(VehicleStatus::class)
                    ->default(VehicleStatus::Active->value),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export')
                    ->exporter(VehicleCostExporter::class),
            ]);
    }
}
