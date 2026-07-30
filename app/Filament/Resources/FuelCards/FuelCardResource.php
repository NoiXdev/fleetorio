<?php

namespace App\Filament\Resources\FuelCards;

use App\Filament\Resources\FuelCards\Pages\CreateFuelCard;
use App\Filament\Resources\FuelCards\Pages\EditFuelCard;
use App\Filament\Resources\FuelCards\Pages\ListFuelCards;
use App\Filament\Resources\FuelCards\Pages\ViewFuelCard;
use App\Filament\Resources\FuelCards\Schemas\FuelCardForm;
use App\Filament\Resources\FuelCards\Schemas\FuelCardInfolist;
use App\Filament\Resources\FuelCards\Tables\FuelCardsTable;
use App\Models\FuelCard;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FuelCardResource extends Resource
{
    protected static ?string $model = FuelCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?string $modelLabel = 'Tankkarte';

    protected static ?string $pluralModelLabel = 'Tankkarten';

    public static function form(Schema $schema): Schema
    {
        return FuelCardForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FuelCardInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FuelCardsTable::configure($table);
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
            'index' => ListFuelCards::route('/'),
            'create' => CreateFuelCard::route('/create'),
            'view' => ViewFuelCard::route('/{record}'),
            'edit' => EditFuelCard::route('/{record}/edit'),
        ];
    }
}
