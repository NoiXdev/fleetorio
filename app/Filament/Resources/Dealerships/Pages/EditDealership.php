<?php

namespace App\Filament\Resources\Dealerships\Pages;

use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\Dealerships\DealershipResource;
use Filament\Resources\Pages\EditRecord;

class EditDealership extends EditRecord
{
    protected static string $resource = DealershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GuardedDeleteAction::make(),
        ];
    }
}
