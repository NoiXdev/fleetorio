<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\Vehicles\VehicleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVehicle extends ViewRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Filament bindet die Sichtbarkeit dieser Action bereits automatisch
            // an VehiclePolicy::update (siehe Filament\Resources\Pages\Page::
            // getDefaultActionAuthorizationResponse(), Fall EditAction). Ein
            // Nur-Lese-Nutzer sieht die Schaltflaeche also gar nicht erst, ganz
            // ohne eigenes ->visible() hier.
            //
            // ->url()/->modal(false): ohne diese beiden fuehrt die Standard-
            // Action zu einem Bearbeiten-Modal mit dem eingebetteten
            // Formular (EditAction setzt in ihrem eigenen setUp() einen
            // modalHeading, wodurch CanOpenModal::shouldOpenModal() ohne
            // weiteres Zutun bereits true liefert). Gewuenscht ist hier aber
            // eine echte Weiterleitung zur eigenstaendigen Bearbeitungsseite
            // (Auftraggeber-Entscheidung, siehe Spezifikation) - genau wie
            // beim Klick auf die Zeile in der Fahrzeugliste.
            EditAction::make()
                ->label('Daten bearbeiten')
                ->url(fn (): string => EditVehicle::getUrl(['record' => $this->getRecord()]))
                ->modal(false),
        ];
    }
}
