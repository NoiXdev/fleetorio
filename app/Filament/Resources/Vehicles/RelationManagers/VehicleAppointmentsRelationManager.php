<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use App\Enums\AppointmentType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Anlegen/Bearbeiten/Loeschen sind ueber App\Policies\VehicleAppointmentPolicy
 * nur Administratoren vorbehalten - Filament prueft das automatisch anhand
 * der Standard-Actions unten (CreateAction/EditAction/DeleteAction), siehe
 * RelationManager::getDefaultActionAuthorizationResponse().
 */
class VehicleAppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    // "Historie" statt "Termine", weil hier nicht nur planbare Ereignisse
    // (Service, Werkstatt, Hauptuntersuchung) landen, sondern auch nicht
    // planbare wie Unfall und Schaden. Klassen- und Tabellenname bleiben
    // absichtlich unveraendert (VehicleAppointment, vehicle_appointments):
    // eine Umbenennung waere eine Migration und aendert fuer Nutzer nichts
    // Sichtbares.
    protected static ?string $title = 'Historie';

    /**
     * Siehe Kommentar an VehicleDocumentsRelationManager::$isLazy - dasselbe
     * gilt hier: beide Listen sollen sofort untereinander erscheinen, nicht
     * erst per Intersection-Observer nachgeladen werden.
     */
    protected static bool $isLazy = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Art')
                    ->options(AppointmentType::class)
                    ->native(false)
                    ->placeholder('Auswählen')
                    ->required(),
                DatePicker::make('date')
                    ->label('Datum')
                    ->required(),
                TextInput::make('mileage')
                    ->label('Kilometerstand')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('km'),
                TextInput::make('cost')
                    ->label('Kosten')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€')
                    // Beträge nur mit dem Recht viewCosts - die Rolle allein
                    // genügt nicht (ein Administrator kann can_view_costs =
                    // false haben, siehe User::canViewCosts()). Anders als im
                    // Fahrzeugformular gibt es hier keine umschliessende,
                    // versteckte Section, die das Verwerfen des Feldwerts
                    // uebernimmt - das eigene dehydrated() unten ist hier
                    // also der tatsaechliche Schutz vor Datenverlust, nicht
                    // nur eine zusaetzliche Absicherung.
                    ->visible(fn (): bool => auth()->user()?->canViewCosts() ?? false)
                    ->dehydrated(fn (): bool => auth()->user()?->canViewCosts() ?? false),
                Textarea::make('notes')
                    ->label('Notiz')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Art')
                    ->badge(),
                TextColumn::make('mileage')
                    ->label('Kilometerstand')
                    ->numeric()
                    ->suffix(' km')
                    ->placeholder('—'),
                TextColumn::make('cost')
                    ->label('Kosten')
                    ->money('EUR')
                    // Beträge nur mit dem Recht viewCosts - die Rolle allein
                    // genügt nicht. Siehe Kommentar am gleichnamigen Feld in
                    // form() oben.
                    ->visible(fn (): bool => auth()->user()?->canViewCosts() ?? false),
                TextColumn::make('notes')
                    ->label('Notiz')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
