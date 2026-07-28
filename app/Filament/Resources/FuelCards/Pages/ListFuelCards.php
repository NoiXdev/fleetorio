<?php

namespace App\Filament\Resources\FuelCards\Pages;

use App\Filament\Resources\FuelCards\FuelCardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFuelCards extends ListRecords
{
    protected static string $resource = FuelCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
