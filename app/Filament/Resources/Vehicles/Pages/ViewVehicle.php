<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\Vehicles\Schemas\VehicleInfolist;
use App\Filament\Resources\Vehicles\VehicleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

/**
 * Abschnittsreihenfolge (Auftraggeber-Wunsch): ... Kosten, Dokumente,
 * Termine, Notizen - dieselbe Begruendung und derselbe Mechanismus wie bei
 * EditVehicle (siehe Kommentar dort): "Notizen" wird als eigene, zweite
 * Schema ('notesInfolist') registriert und erst nach den Relationsmanagern
 * eingebettet. Hier ist es sogar einfacher als beim Formular, weil eine
 * Infolist nichts speichert - keine mutateFormDataBeforeSave()-Ergaenzung
 * noetig, die zweite Schema traegt sich selbst.
 */
class ViewVehicle extends ViewRecord
{
    protected static string $resource = VehicleResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(1)
                ->columnSpanFull()
                ->schema(VehicleInfolist::mainSections()),
        ]);
    }

    public function notesInfolist(Schema $schema): Schema
    {
        return $schema->components([VehicleInfolist::notesSection()]);
    }

    protected function defaultNotesInfolist(Schema $schema): Schema
    {
        return $schema->record($this->getRecord());
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->hasInfolist()
                ? $this->getInfolistContentComponent()
                : $this->getFormContentComponent(),
            $this->getRelationManagersContentComponent(),
            EmbeddedSchema::make('notesInfolist'),
        ]);
    }

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
