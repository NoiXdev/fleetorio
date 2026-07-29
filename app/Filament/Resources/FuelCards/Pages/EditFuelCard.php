<?php

namespace App\Filament\Resources\FuelCards\Pages;

use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\FuelCards\FuelCardResource;
use Filament\Resources\Pages\EditRecord;

class EditFuelCard extends EditRecord
{
    protected static string $resource = FuelCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GuardedDeleteAction::make(),
        ];
    }
}
