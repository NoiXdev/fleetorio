<?php

namespace App\Filament\Resources\Dealerships;

use App\Filament\Resources\Dealerships\Pages\CreateDealership;
use App\Filament\Resources\Dealerships\Pages\EditDealership;
use App\Filament\Resources\Dealerships\Pages\ListDealerships;
use App\Filament\Resources\Dealerships\Schemas\DealershipForm;
use App\Filament\Resources\Dealerships\Tables\DealershipsTable;
use App\Models\Dealership;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DealershipResource extends Resource
{
    protected static ?string $model = Dealership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?string $modelLabel = 'Autohaus';

    protected static ?string $pluralModelLabel = 'Autohäuser';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DealershipForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DealershipsTable::configure($table);
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
            'index' => ListDealerships::route('/'),
            'create' => CreateDealership::route('/create'),
            'edit' => EditDealership::route('/{record}/edit'),
        ];
    }
}
