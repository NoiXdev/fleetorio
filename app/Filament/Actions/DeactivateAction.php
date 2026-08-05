<?php

namespace App\Filament\Actions;

use App\Filament\Actions\Concerns\TracksActiveState;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Tritt an die Stelle des Loeschens, solange ein Datensatz aktiv ist.
 *
 * Die Anwendung verwirft Stammdaten nicht mehr sofort: erst wird deaktiviert,
 * und nur ein inaktiver Datensatz laesst sich ueberhaupt loeschen (siehe
 * GuardedDeleteAction). Deaktivieren selbst ist immer erlaubt - auch bei einem
 * Mitarbeiter, dem noch ein Fahrzeug zugeordnet ist. Das spiegelt die
 * Wirklichkeit: jemand verlaesst die Firma, das Auto bleibt ihm zugeordnet,
 * bis ein Nachfolger da ist.
 */
class DeactivateAction extends Action
{
    use TracksActiveState;

    public static function getDefaultName(): ?string
    {
        return 'deactivate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Deaktivieren');
        $this->icon('heroicon-m-archive-box');
        $this->color('gray');
        $this->requiresConfirmation();
        $this->modalHeading('Eintrag deaktivieren');
        $this->modalDescription(
            'Der Eintrag bleibt vollständig erhalten und behält seine Zuordnungen. '
            .'Er taucht nur nicht mehr in Auswahllisten und in der Standardansicht auf. '
            .'Löschen ist danach möglich.'
        );
        $this->modalSubmitActionLabel('Deaktivieren');

        $this->visible(fn (Model $record): bool => $this->isRecordActive($record));

        $this->action(function (Model $record): void {
            $this->setRecordState($record, $this->inactiveValue);

            Notification::make()
                ->success()
                ->title('Eintrag deaktiviert')
                ->send();
        });
    }
}
