<?php

namespace App\Filament\Resources\VehicleCosts;

use App\Filament\Resources\VehicleCosts\Pages\ListVehicleCosts;
use App\Filament\Resources\VehicleCosts\Tables\VehicleCostsTable;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Zweite Resource auf dem Vehicle-Model: reine Auswertung der Kosten, komplett
 * hinter dem Recht viewCosts. Kosten werden am Fahrzeug gepflegt, hier nur
 * gelesen - daher keine Create-/Edit-Seiten und kein Formular.
 */
class VehicleCostResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    // Pflicht: sonst kollidiert die Route mit der VehicleResource.
    protected static ?string $slug = 'costs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Fuhrpark';

    protected static ?string $navigationLabel = 'Kosten';

    protected static ?string $modelLabel = 'Kostenübersicht';

    protected static ?string $pluralModelLabel = 'Kostenübersicht';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return VehicleCostsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewCosts') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleCosts::route('/'),
        ];
    }
}
