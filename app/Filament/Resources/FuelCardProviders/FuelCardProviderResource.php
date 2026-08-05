<?php

namespace App\Filament\Resources\FuelCardProviders;

use App\Filament\Resources\FuelCardProviders\Pages\CreateFuelCardProvider;
use App\Filament\Resources\FuelCardProviders\Pages\EditFuelCardProvider;
use App\Filament\Resources\FuelCardProviders\Pages\ListFuelCardProviders;
use App\Filament\Resources\FuelCardProviders\Pages\ViewFuelCardProvider;
use App\Filament\Resources\FuelCardProviders\Schemas\FuelCardProviderForm;
use App\Filament\Resources\FuelCardProviders\Schemas\FuelCardProviderInfolist;
use App\Filament\Resources\FuelCardProviders\Tables\FuelCardProvidersTable;
use App\Models\FuelCardProvider;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class FuelCardProviderResource extends Resource
{
    protected static ?string $model = FuelCardProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?string $modelLabel = 'Tankkartenanbieter';

    protected static ?string $pluralModelLabel = 'Tankkartenanbieter';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FuelCardProviderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FuelCardProviderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FuelCardProvidersTable::configure($table);
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
            'index' => ListFuelCardProviders::route('/'),
            'create' => CreateFuelCardProvider::route('/create'),
            'view' => ViewFuelCardProvider::route('/{record}'),
            'edit' => EditFuelCardProvider::route('/{record}/edit'),
        ];
    }

    /**
     * Inaktive Stammdaten sollen im Alltag nicht auftauchen - auch nicht ueber
     * die globale Suche, die sonst der einzige Weg waere, sie unbeabsichtigt
     * wieder in Umlauf zu bringen.
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->where('active', true);
    }
}
