<?php

namespace App\Filament\Resources\FuelCards\Pages;

use App\Filament\Resources\FuelCards\FuelCardResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFuelCard extends ViewRecord
{
    protected static string $resource = FuelCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Siehe App\Filament\Resources\Vehicles\Pages\ViewVehicle fuer die
            // Begruendung von ->url()/->modal(false).
            EditAction::make()
                ->label('Daten bearbeiten')
                ->url(fn (): string => EditFuelCard::getUrl(['record' => $this->getRecord()]))
                ->modal(false),
        ];
    }
}
