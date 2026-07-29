<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fahrzeug')
                    ->columns(2)
                    ->schema([
                        TextInput::make('brand')
                            ->label('Marke')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('model')
                            ->label('Modell')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('license_plate')
                            ->label('Kennzeichen')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('fuel_type')
                            ->label('Antriebsart')
                            ->options(FuelType::class)
                            ->native(false)
                            // Kein Standardwert - ohne eigenen Platzhalter würde
                            // Filaments deutsche Übersetzung "Wählen Sie eine
                            // Option" zeigen (vendor/filament/forms/resources/
                            // lang/de/components.php, select.placeholder).
                            ->placeholder('Auswählen')
                            ->required(),
                        Select::make('status')
                            ->label('Status')
                            ->options(VehicleStatus::class)
                            ->native(false)
                            // Hat einen Standardwert - ein Platzhalter würde hier
                            // nie angezeigt, daher bewusst kein ->placeholder().
                            ->default(VehicleStatus::Active->value)
                            ->required(),
                    ]),

                Section::make('Zuordnung')
                    ->columns(2)
                    ->schema([
                        Select::make('employee_id')
                            ->label('Mitarbeiter')
                            ->relationship('employee', 'last_name')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => $record->full_name)
                            ->searchable(['first_name', 'last_name'])
                            ->placeholder('Auswählen')
                            ->preload(),
                        Select::make('fuel_card_id')
                            ->label('Tankkarte')
                            // Die Kartennummer ist verschluesselt und im Model
                            // als $hidden gefuehrt; die Auswahl zeigt daher nur
                            // Anbieter plus die letzten vier Stellen, nicht die
                            // volle Nummer im Klartext.
                            ->relationship('fuelCard', 'card_number')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => $record->maskedNumber())
                            ->placeholder('Auswählen')
                            ->preload(),
                    ]),

                Section::make('Vertrag')
                    ->columns(3)
                    ->schema([
                        Select::make('leasing_company_id')
                            ->label('Leasinggeber')
                            ->relationship('leasingCompany', 'name')
                            ->searchable()
                            ->placeholder('Auswählen')
                            ->preload(),
                        // Native statt Filaments eigenem Picker: Der eigene Picker
                        // rendert das sichtbare Feld als readonly-Input in einem
                        // Button (vendor/filament/forms/src/Components/
                        // DateTimePicker.php) - eine Tastatureingabe ist dort nicht
                        // moeglich. Das native Feld ist es und zeigt im deutschen
                        // Browser TT.MM.JJJJ; ->displayFormat() wirkt bei ihm nicht
                        // und entfaellt daher.
                        DatePicker::make('lease_start')
                            ->label('Leasingstart'),
                        DatePicker::make('lease_end')
                            ->label('Leasingende')
                            ->after('lease_start'),
                    ]),

                Section::make('Kosten')
                    ->columns(3)
                    // Der eigentliche Schutz vor Datenverlust entsteht bereits hier:
                    // Wenn diese Section versteckt ist, meldet ihr isDehydrated()
                    // false (Filament dehydriert eine ausgeblendete Komponente nur,
                    // wenn dehydratedWhenHidden() explizit gesetzt ist), und dieser
                    // Zustand wird an alle Kind-Komponenten weitergereicht. Beim
                    // Aufbau des gespeicherten Formularzustands werden deren
                    // Statuspfade dadurch verworfen (Arr::forget), bevor das
                    // dehydrated()-Flag der einzelnen Felder unten überhaupt
                    // ausgewertet wird. Siehe vendor/filament/schemas/src/
                    // Components/Concerns/HasState.php, dehydrateState()
                    // (Zeilen 316-356) und isHiddenAndNotDehydratedWhenHidden().
                    ->visible(fn (): bool => auth()->user()?->canViewCosts() ?? false)
                    ->schema([
                        TextInput::make('monthly_lease_rate')
                            ->label('Leasingrate (monatlich)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            // Verteidigung in der Tiefe: greift nur, falls dieses
                            // Feld je aus der Section herausgelöst und nur noch
                            // feldweise versteckt wird. Der eigentliche Schutz
                            // (siehe Kommentar an der Section oben) wirkt bereits
                            // vorher.
                            ->dehydrated(fn (): bool => auth()->user()?->canViewCosts() ?? false),
                        TextInput::make('annual_vehicle_tax')
                            ->label('Kfz-Steuer (jährlich)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->dehydrated(fn (): bool => auth()->user()?->canViewCosts() ?? false),
                        TextInput::make('annual_insurance')
                            ->label('Versicherung (jährlich)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->dehydrated(fn (): bool => auth()->user()?->canViewCosts() ?? false),
                    ]),

                Section::make('Notizen')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notizen')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
