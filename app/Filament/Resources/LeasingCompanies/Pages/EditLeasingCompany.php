<?php

namespace App\Filament\Resources\LeasingCompanies\Pages;

use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\LeasingCompanies\LeasingCompanyResource;
use Filament\Resources\Pages\EditRecord;

class EditLeasingCompany extends EditRecord
{
    protected static string $resource = LeasingCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GuardedDeleteAction::make(),
        ];
    }
}
