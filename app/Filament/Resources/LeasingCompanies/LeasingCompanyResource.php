<?php

namespace App\Filament\Resources\LeasingCompanies;

use App\Filament\Resources\LeasingCompanies\Pages\CreateLeasingCompany;
use App\Filament\Resources\LeasingCompanies\Pages\EditLeasingCompany;
use App\Filament\Resources\LeasingCompanies\Pages\ListLeasingCompanies;
use App\Filament\Resources\LeasingCompanies\Pages\ViewLeasingCompany;
use App\Filament\Resources\LeasingCompanies\Schemas\LeasingCompanyForm;
use App\Filament\Resources\LeasingCompanies\Schemas\LeasingCompanyInfolist;
use App\Filament\Resources\LeasingCompanies\Tables\LeasingCompaniesTable;
use App\Models\LeasingCompany;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LeasingCompanyResource extends Resource
{
    protected static ?string $model = LeasingCompany::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?string $modelLabel = 'Leasinggeber';

    protected static ?string $pluralModelLabel = 'Leasinggeber';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return LeasingCompanyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LeasingCompanyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeasingCompaniesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeasingCompanies::route('/'),
            'create' => CreateLeasingCompany::route('/create'),
            'view' => ViewLeasingCompany::route('/{record}'),
            'edit' => EditLeasingCompany::route('/{record}/edit'),
        ];
    }
}
