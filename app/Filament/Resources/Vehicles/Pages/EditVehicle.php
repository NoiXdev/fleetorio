<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Enums\VehicleStatus;
use App\Filament\Resources\Vehicles\Schemas\VehicleForm;
use App\Filament\Resources\Vehicles\VehicleResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

/**
 * Knopfreihen-Layout ist Auftraggeber-Wunsch (siehe Spezifikation):
 *
 * - Oben rechts (Header): "Speichern" - die am haeufigsten gebrauchte
 *   Aktion an der sichtbarsten Stelle. Speichert OHNE die Seite zu
 *   verlassen (shouldRedirect: false), damit sie sich klar von der
 *   Aktion unten unterscheidet - siehe getFormActions() unten.
 * - Unten rechts (Formularfuss, siehe getFormActionsAlignment()):
 *   "Speichern und schließen" (speichert UND verlaesst die Seite,
 *   siehe EditRecord::save()), "Abbrechen" (verwirft Aenderungen), und
 *   ganz rechts aussen "Löschen" - bewusst zurueckhaltend gestaltet
 *   (->outlined() statt gefuellt), damit sie erreichbar bleibt, aber
 *   nicht wie die Hauptaktion aussieht. Die Sicherheitsabfrage bleibt
 *   ueber DeleteAction unveraendert erhalten.
 *
 * Abschnittsreihenfolge (Auftraggeber-Wunsch): ... Kosten, Dokumente,
 * Termine, Notizen. Filament rendert eingebettete Relationsmanager
 * (Dokumente, Termine) grundsaetzlich erst NACH der kompletten 'form'-
 * Schema (siehe EditRecord::content()) - ein einzelner EmbeddedSchema-Block
 * laesst sich nicht von innen aufteilen, um Relationsmanager zwischen
 * "Kosten" und "Notizen" zu schieben. "Notizen" wird deshalb als eigene,
 * zweite Schema ('notesForm') registriert und erst NACH den
 * Relationsmanagern eingebettet (siehe content() unten) - dieselbe generische
 * Mechanik, mit der Filament selbst 'form' und 'infolist' registriert
 * (Filament\Schemas\Concerns\InteractsWithSchemas::cacheSchema(), sucht nach
 * einer Methode mit dem Schema-Namen plus optional 'default'.Name() fuer
 * Grundeinstellungen). Da beide Schemas denselben statePath 'data' teilen,
 * landet ein Eintrag im Notizfeld ganz normal im Livewire-Zustand
 * $this->data['notes'] - nur getState() der 'form'-Schema kennt das Feld
 * nicht mehr (es ist nicht mehr Teil ihrer Komponenten), weshalb
 * mutateFormDataBeforeSave() es von Hand wieder in die zu speichernden Daten
 * einsetzt. "Notizen" hat keine eigene Validierung, daher ist hier keine
 * zweite Fehlerbehandlung noetig.
 */
class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(1)
                ->columnSpanFull()
                ->schema(VehicleForm::mainSections()),
        ]);
    }

    public function notesForm(Schema $schema): Schema
    {
        return $schema->components([VehicleForm::notesSection()]);
    }

    /**
     * Dieselben Grundeinstellungen wie Filaments eigene defaultForm() fuer
     * die 'form'-Schema (siehe EditRecord::defaultForm()) - derselbe
     * statePath 'data', damit ein Eintrag im Notizfeld im selben
     * Livewire-Zustand landet wie alle anderen Formularfelder.
     */
    protected function defaultNotesForm(Schema $schema): Schema
    {
        return $schema
            ->model($this->getRecord())
            ->operation('edit')
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            $this->getRelationManagersContentComponent(),
            EmbeddedSchema::make('notesForm'),
        ]);
    }

    /**
     * EditRecord::mount() fuellt nur die 'form'-Schema (siehe
     * fillFormWithDataAndCallHooks(), $this->form->fill(...)). Ohne diese
     * Ergaenzung wuerde das Notizen-Textfeld beim Laden der Seite leer
     * erscheinen, obwohl $this->data['notes'] bereits korrekt gesetzt ist -
     * das Feld braucht den eigenen hydrateState()-Durchlauf von fill(), um
     * seinen intern zwischengespeicherten Anzeigewert zu setzen.
     *
     * fillPartially() statt fill(): fill() ruft intern rawState() auf, die
     * bei gemeinsamem statePath 'data' den GESAMTEN $this->data-Wert
     * ueberschreibt (data_set($livewire, 'data', $state), siehe
     * Filament\Schemas\Concerns\HasState::rawState()) - das wuerde den
     * bereits von der 'form'-Schema hydrierten Bilddateizustand
     * (image_path, ein Array mit generiertem Schluessel statt des rohen
     * Pfad-Strings) wieder durch den rohen Modellwert ersetzen und
     * BaseFileUpload::getUploadedFiles() zum Absturz bringen
     * ("foreach() argument must be of type array|object, string given").
     * fillPartially() setzt dagegen gezielt nur 'data.notes'.
     */
    protected function fillForm(): void
    {
        parent::fillForm();

        $this->getSchema('notesForm')->fillPartially(
            $this->getRecord()->attributesToArray(),
            ['notes'],
        );
    }

    /**
     * getState() der 'form'-Schema kennt "notes" nicht mehr (siehe
     * Klassenkommentar oben) - $this->data['notes'] traegt den aktuellen
     * Wert trotzdem korrekt, weil die Notizen-Schema denselben statePath
     * 'data' verwendet und Livewire das Feld unabhaengig von der Schema-
     * Zugehoerigkeit synchronisiert.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['notes'] = $this->data['notes'] ?? null;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Speichern')
                // Header-Actions liegen ausserhalb des <form>-Tags (siehe
                // EditRecord::getFormActionsContentComponent()) - ->submit()
                // (HTML-Formularabsenden) griffe hier ins Leere. ->action()
                // ruft die Livewire-Methode save() direkt auf, unabhaengig
                // von der DOM-Position; die Feldwerte sind ueber wire:model
                // ohnehin bereits am Komponentenzustand gebunden.
                ->action(fn (): mixed => $this->save(shouldRedirect: false))
                ->keyBindings(['mod+s']),
        ];
    }

    public function getFormActionsAlignment(): Alignment|string
    {
        return Alignment::End;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveAndCloseFormAction(),
            $this->getCancelFormAction(),
            $this->deleteAction(),
        ];
    }

    protected function getSaveAndCloseFormAction(): Action
    {
        return Action::make('saveAndClose')
            ->label('Speichern und schließen')
            // save() mit dem Standardwert shouldRedirect: true - verlaesst
            // die Seite nach dem Speichern (zurueck zur Anzeige- oder
            // Listenseite, siehe getRedirectUrl() unten), anders als die
            // Kopfzeilen-Aktion oben.
            ->submit('save');
    }

    /**
     * Filaments eigene getRedirectUrl() (siehe vendor/filament/filament/src/
     * Resources/Pages/EditRecord.php) liefert absichtlich null - also KEIN
     * Redirect -, solange der Benutzer die Seite weiterhin bearbeiten darf
     * (das ist der Normalfall direkt nach dem Speichern). Genau das will
     * "Speichern und schließen" nicht: es soll IMMER zur Anzeige- oder
     * Listenseite zurueckfuehren. Diese Fassung uebernimmt nur den
     * Fallback-Zweig von Filaments Original (view-Seite, falls vorhanden und
     * erlaubt, sonst die Liste) und laesst den "bleib auf der Seite"-Zweig
     * weg. Wirkt nur bei shouldRedirect: true - die Kopfzeilen-Aktion oben
     * ruft save(shouldRedirect: false) auf und ueberspringt diese Methode
     * dadurch ohnehin komplett (siehe EditRecord::save()).
     */
    protected function getRedirectUrl(): ?string
    {
        $resource = static::getResource();

        if ($resource::hasPage('view') && $resource::canView($this->getRecord())) {
            return $this->getResourceUrl('view', $this->getRedirectUrlParameters());
        }

        return $this->getResourceUrl(parameters: $this->getRedirectUrlParameters());
    }

    /**
     * Der Name ist bewusst "deleteAction" (nicht "getDeleteFormAction"):
     * Filaments generische Action-Aufloesung (assertActionExists/callAction
     * in Tests, aber auch das tatsaechliche mountAction() beim Klick im
     * Browser) sucht bei einer Aktion ohne eigenen Schema-Kontext ausser in
     * $this->cachedActions auch nach einer Methode {name}Action() auf der
     * Livewire-Komponente (siehe Filament\Actions\Concerns\
     * InteractsWithActions::resolveAction()). Header-Actions werden separat
     * ueber cacheAction() registriert (InteractsWithHeaderActions::
     * cacheInteractsWithHeaderActions()) - Formularfuss-Aktionen wie diese
     * hier durchlaufen das nicht automatisch. DeleteAction erzwingt eine
     * Bestaetigung und damit den vollen mountAction()-Ablauf; ohne diesen
     * Methodennamen waere der Knopf weder testbar noch im echten Browser
     * klickbar.
     *
     * Sichtbar nur bei einem ausgemusterten Fahrzeug: Loeschen ist der zweite
     * Schritt, davor steht das Ausmustern (DeactivateAction in der
     * Fahrzeugliste). Diese Bedingung gehoert genau hierher und nirgends sonst
     * - die Fahrzeugliste bietet gar kein Loeschen an, es haengt
     * ausschliesslich an diesem Knopf. Ohne sie waere die Regel an der
     * einzigen Stelle umgehbar, an der man ein Fahrzeug tatsaechlich loescht.
     */
    public function deleteAction(): Action
    {
        return DeleteAction::make()
            ->outlined()
            ->color('danger')
            ->modalHeading('Fahrzeug endgültig löschen')
            ->visible(fn (): bool => $this->getRecord()->status === VehicleStatus::Retired);
    }
}
