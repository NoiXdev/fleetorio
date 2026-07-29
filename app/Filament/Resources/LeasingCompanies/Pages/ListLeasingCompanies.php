<?php

namespace App\Filament\Resources\LeasingCompanies\Pages;

use App\Filament\Resources\LeasingCompanies\LeasingCompanyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeasingCompanies extends ListRecords
{
    protected static string $resource = LeasingCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
