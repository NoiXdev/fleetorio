<?php

namespace App\Filament\Resources\FuelCardProviders\Pages;

use App\Filament\Resources\FuelCardProviders\FuelCardProviderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFuelCardProvider extends ViewRecord
{
    protected static string $resource = FuelCardProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Siehe App\Filament\Resources\Vehicles\Pages\ViewVehicle fuer die
            // Begruendung von ->url()/->modal(false).
            EditAction::make()
                ->label('Daten bearbeiten')
                ->url(fn (): string => EditFuelCardProvider::getUrl(['record' => $this->getRecord()]))
                ->modal(false),
        ];
    }
}
