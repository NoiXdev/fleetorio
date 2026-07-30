<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Models\Employee;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Zeigt alle erfassten Felder, auch die, die in der Liste fehlen (email).
 * Das ist der ganze Zweck dieser Seite: einem Nur-Lese-Nutzer (typischerweise
 * die Buchhaltung) einen Weg zu diesen Angaben zu geben, ohne die
 * Bearbeitungsseite zu betreten, die Administratorrechte verlangt.
 */
class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mitarbeiter')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('full_name')
                            ->label('Name'),
                        TextEntry::make('vehicles_count')
                            ->label('Fahrzeuge')
                            ->state(fn (Employee $record): string => (string) $record->vehicles()->count()),
                        TextEntry::make('first_name')
                            ->label('Vorname'),
                        TextEntry::make('last_name')
                            ->label('Nachname'),
                        TextEntry::make('department')
                            ->label('Abteilung')
                            ->placeholder('—'),
                        TextEntry::make('email')
                            ->label('E-Mail')
                            ->placeholder('—'),
                        IconEntry::make('active')
                            ->label('Aktiv')
                            ->boolean(),
                    ]),
            ]);
    }
}
