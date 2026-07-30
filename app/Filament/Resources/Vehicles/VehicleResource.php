<?php

namespace App\Filament\Resources\Vehicles;

use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Resources\Vehicles\Pages\ViewVehicle;
use App\Filament\Resources\Vehicles\RelationManagers\VehicleAppointmentsRelationManager;
use App\Filament\Resources\Vehicles\RelationManagers\VehicleDocumentsRelationManager;
use App\Filament\Resources\Vehicles\Schemas\VehicleForm;
use App\Filament\Resources\Vehicles\Schemas\VehicleInfolist;
use App\Filament\Resources\Vehicles\Tables\VehiclesTable;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Fuhrpark';

    protected static ?string $modelLabel = 'Fahrzeug';

    protected static ?string $pluralModelLabel = 'Fahrzeuge';

    protected static ?string $recordTitleAttribute = 'license_plate';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return VehicleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VehicleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehiclesTable::configure($table);
    }

    /**
     * Dokumente und Termine sollen laut Spezifikation untereinander stehen,
     * nicht als Tabs. Filament rendert automatisch Tabs, sobald mehr als ein
     * Eintrag in diesem Array steht (siehe Filament\Resources\Pages\Concerns\
     * HasRelationManagers::getRelationManagersContentComponent(), Bedingung
     * "count($managers) > 1"). Eine einzelne RelationGroup zaehlt hier als
     * genau ein Eintrag: faellt man in den nicht-tabbed Zweig derselben
     * Methode, wird eine RelationGroup ueber Group::make(...) aufgeloest -
     * ihre enthaltenen Relation Manager erscheinen dadurch einfach
     * nacheinander, ohne eigene Tab-Navigation. Die Reihenfolge hier
     * (Dokumente vor Terminen) legt die Anzeigereihenfolge fest.
     */
    public static function getRelations(): array
    {
        return [
            RelationGroup::make('Dokumente & Termine', [
                VehicleDocumentsRelationManager::class,
                VehicleAppointmentsRelationManager::class,
            ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'view' => ViewVehicle::route('/{record}'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
