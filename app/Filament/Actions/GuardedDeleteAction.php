<?php

namespace App\Filament\Actions;

use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Löschen von Stammdaten, die noch einem Fahrzeug zugeordnet sind, würde auf
 * Datenbankebene an den Foreign Keys scheitern. Diese Action fängt den Fall
 * vorher ab und erklärt verständlich, welches Fahrzeug im Weg steht.
 */
class GuardedDeleteAction extends DeleteAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->before(function (GuardedDeleteAction $action, Model $record): void {
            $vehicle = $record->vehicles()->first();

            if ($vehicle === null) {
                return;
            }

            $count = $record->vehicles()->count();

            Notification::make()
                ->danger()
                ->title('Löschen nicht möglich')
                ->body($count === 1
                    ? "Der Eintrag ist noch dem Fahrzeug {$vehicle->license_plate} zugeordnet."
                    : "Der Eintrag ist noch {$count} Fahrzeugen zugeordnet, unter anderem {$vehicle->license_plate}.")
                ->persistent()
                ->send();

            $action->halt();
        });
    }
}
