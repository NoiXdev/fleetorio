<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Siehe App\Filament\Resources\Vehicles\Pages\ViewVehicle fuer die
            // Begruendung von ->url()/->modal(false): echte Weiterleitung zur
            // eigenstaendigen Bearbeitungsseite statt eines Modals. Die
            // Sichtbarkeit fuer Nur-Lese-Nutzer bindet Filament automatisch an
            // EmployeePolicy::update.
            EditAction::make()
                ->label('Daten bearbeiten')
                ->url(fn (): string => EditEmployee::getUrl(['record' => $this->getRecord()]))
                ->modal(false),
        ];
    }
}
