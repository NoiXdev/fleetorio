<?php

namespace App\Filament\Resources\LeasingCompanies\Pages;

use App\Filament\Resources\LeasingCompanies\LeasingCompanyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLeasingCompany extends ViewRecord
{
    protected static string $resource = LeasingCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Siehe App\Filament\Resources\Vehicles\Pages\ViewVehicle fuer die
            // Begruendung von ->url()/->modal(false).
            EditAction::make()
                ->label('Daten bearbeiten')
                ->url(fn (): string => EditLeasingCompany::getUrl(['record' => $this->getRecord()]))
                ->modal(false),
        ];
    }
}
