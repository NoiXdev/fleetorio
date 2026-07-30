<?php

namespace App\Filament\Resources\Dealerships\Pages;

use App\Filament\Resources\Dealerships\DealershipResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDealership extends ViewRecord
{
    protected static string $resource = DealershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Siehe App\Filament\Resources\Vehicles\Pages\ViewVehicle fuer die
            // Begruendung von ->url()/->modal(false).
            EditAction::make()
                ->label('Daten bearbeiten')
                ->url(fn (): string => EditDealership::getUrl(['record' => $this->getRecord()]))
                ->modal(false),
        ];
    }
}
