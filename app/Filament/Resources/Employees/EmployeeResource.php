<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\Pages\ViewEmployee;
use App\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Filament\Resources\Employees\Schemas\EmployeeInfolist;
use App\Filament\Resources\Employees\Tables\EmployeesTable;
use App\Models\Employee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?string $modelLabel = 'Mitarbeiter';

    protected static ?string $pluralModelLabel = 'Mitarbeiter';

    protected static ?string $recordTitleAttribute = 'last_name';

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeesTable::configure($table);
    }

    /**
     * Vor- und Nachname (Auftraggeber-Wunsch), nicht nur $recordTitleAttribute
     * ('last_name') wie im Standardverhalten von Resource::
     * getGloballySearchableAttributes().
     *
     * @return array<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name'];
    }

    /**
     * Voller Name statt nur des Nachnamens (der Standard-Titel ueber
     * $recordTitleAttribute) - zwei Mitarbeiter mit demselben Nachnamen waeren
     * sonst im Suchergebnis nicht zu unterscheiden.
     */
    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var Employee $record */
        return $record->full_name;
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
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'view' => ViewEmployee::route('/{record}'),
            'edit' => EditEmployee::route('/{record}/edit'),
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
