<?php

namespace App\Filament\Resources\VehicleCosts\Pages;

use App\Filament\Resources\VehicleCosts\VehicleCostResource;
use Filament\Resources\Pages\ListRecords;

class ListVehicleCosts extends ListRecords
{
    protected static string $resource = VehicleCostResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
