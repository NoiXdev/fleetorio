<?php

namespace App\Filament\Actions;

use App\Filament\Actions\Concerns\TracksActiveState;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Gegenstueck zu DeactivateAction. Bewusst ohne Rueckfrage: Aktivieren ist
 * folgenlos umkehrbar, eine Sicherheitsabfrage waere hier nur im Weg.
 */
class ActivateAction extends Action
{
    use TracksActiveState;

    public static function getDefaultName(): ?string
    {
        return 'activate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Aktivieren');
        $this->icon('heroicon-m-arrow-uturn-left');
        $this->color('success');

        $this->visible(fn (Model $record): bool => ! $this->isRecordActive($record));

        $this->action(function (Model $record): void {
            $this->setRecordState($record, $this->activeValue);

            Notification::make()
                ->success()
                ->title('Eintrag aktiviert')
                ->send();
        });
    }
}
