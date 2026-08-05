<?php

namespace App\Filament\Actions\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Der Zustand "aktiv/inaktiv" steckt nicht ueberall in derselben Spalte: das
 * Fahrzeug fuehrt ihn als Enum in `status` (Aktiv/Ausgemustert), die uebrigen
 * Stammdaten als boolesches `active`. Damit sich Deaktivieren, Aktivieren und
 * Loeschen trotzdem gleich verhalten, kapseln alle drei Aktionen den Zugriff
 * hier - und nicht jede fuer sich.
 */
trait TracksActiveState
{
    protected string $stateColumn = 'active';

    protected mixed $inactiveValue = false;

    protected mixed $activeValue = true;

    /**
     * Stellt Spalte und Werte um, z. B. fuer das Fahrzeug:
     * ->stateColumn('status', VehicleStatus::Retired, VehicleStatus::Active)
     */
    public function stateColumn(string $column, mixed $inactiveValue, mixed $activeValue): static
    {
        $this->stateColumn = $column;
        $this->inactiveValue = $inactiveValue;
        $this->activeValue = $activeValue;

        return $this;
    }

    protected function isRecordActive(Model $record): bool
    {
        return $record->{$this->stateColumn} === $this->activeValue;
    }

    protected function setRecordState(Model $record, mixed $value): void
    {
        $record->{$this->stateColumn} = $value;
        $record->save();
    }
}
