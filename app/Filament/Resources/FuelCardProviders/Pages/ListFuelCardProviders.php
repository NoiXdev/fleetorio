<?php

namespace App\Filament\Resources\FuelCardProviders\Pages;

use App\Filament\Resources\FuelCardProviders\FuelCardProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFuelCardProviders extends ListRecords
{
    protected static string $resource = FuelCardProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
