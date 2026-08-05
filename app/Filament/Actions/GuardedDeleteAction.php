<?php

namespace App\Filament\Actions;

use App\Filament\Actions\Concerns\TracksActiveState;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Löschen von Stammdaten, die noch einem Fahrzeug zugeordnet sind, würde auf
 * Datenbankebene an den Foreign Keys scheitern. Diese Action fängt den Fall
 * vorher ab und erklärt verständlich, welches Fahrzeug im Weg steht.
 *
 * Standardmäßig prüft sie die Relation vehicles() des Datensatzes (Autohaus,
 * Leasinggeber, Mitarbeiter, Tankkarte - bei allen haengt das Fahrzeug direkt
 * an ihnen). Beim Tankkartenanbieter ist die naechste Stufe aber eine
 * Tankkarte, kein Fahrzeug (fuel_cards.fuel_card_provider_id, nicht
 * vehicles.*) - dafuer lassen sich Relation, Beschriftung und Meldungstext
 * ueber guardedBy() umstellen, ohne dass sich am Standardverhalten der
 * uebrigen Aufrufer etwas aendert.
 *
 * Seit der Umstellung auf "Deaktivieren statt Loeschen" ist das der zweite
 * Schritt: Ein aktiver Datensatz wird deaktiviert (DeactivateAction), und erst
 * ein inaktiver laesst sich entfernen. Die beiden Bedingungen greifen
 * unabhaengig voneinander - der Zustand entscheidet, ob geloescht werden darf,
 * der Fremdschluessel, ob es technisch geht.
 */
class GuardedDeleteAction extends DeleteAction
{
    use TracksActiveState;

    protected string $guardedRelationshipName = 'vehicles';

    /**
     * @var (Closure(Model): string)|null
     */
    protected ?Closure $guardedRecordLabel = null;

    /**
     * @var (Closure(int, string): string)|null
     */
    protected ?Closure $guardedMessage = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modalHeading('Endgültig löschen');

        // Nur inaktive Datensaetze duerfen ueberhaupt geloescht werden. Wer
        // hier eine weitere visible()-Bedingung ergaenzt, muss diese
        // mitnehmen - sonst ist die Regel an dieser Stelle aufgehoben.
        $this->visible(fn (Model $record): bool => ! $this->isRecordActive($record));

        $this->before(function (GuardedDeleteAction $action, Model $record): void {
            $relationship = $record->{$this->guardedRelationshipName}();
            $relatedRecord = $relationship->first();

            if ($relatedRecord === null) {
                return;
            }

            $count = $relationship->count();
            $label = $this->guardedRecordLabel
                ? ($this->guardedRecordLabel)($relatedRecord)
                : $relatedRecord->license_plate;

            $message = $this->guardedMessage
                ? ($this->guardedMessage)($count, $label)
                : ($count === 1
                    ? "Der Eintrag ist noch dem Fahrzeug {$label} zugeordnet."
                    : "Der Eintrag ist noch {$count} Fahrzeugen zugeordnet, unter anderem {$label}.");

            Notification::make()
                ->danger()
                ->title('Endgültig löschen nicht möglich')
                ->body($message)
                ->persistent()
                ->send();

            $action->halt();
        });
    }

    /**
     * Stellt Relation, Beschriftung des gefundenen Datensatzes und den
     * Meldungstext um, z. B. für den Tankkartenanbieter, dessen naechste
     * Stufe eine Tankkarte statt eines Fahrzeugs ist.
     *
     * @param  Closure(Model): string  $recordLabel
     * @param  Closure(int, string): string  $message
     */
    public function guardedBy(string $relationshipName, Closure $recordLabel, Closure $message): static
    {
        $this->guardedRelationshipName = $relationshipName;
        $this->guardedRecordLabel = $recordLabel;
        $this->guardedMessage = $message;

        return $this;
    }
}
