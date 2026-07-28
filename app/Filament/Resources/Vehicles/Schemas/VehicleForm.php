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
                            ->required(),
                        Select::make('status')
                            ->label('Status')
                            ->options(VehicleStatus::class)
                            ->native(false)
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
                            ->preload(),
                        Select::make('fuel_card_id')
                            ->label('Tankkarte')
                            ->relationship('fuelCard', 'card_number')
                            ->preload(),
                    ]),

                Section::make('Vertrag')
                    ->columns(3)
                    ->schema([
                        Select::make('leasing_company_id')
                            ->label('Leasinggeber')
                            ->relationship('leasingCompany', 'name')
                            ->searchable()
                            ->preload(),
                        DatePicker::make('lease_start')
                            ->label('Leasingstart')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->required(),
                        DatePicker::make('lease_end')
                            ->label('Leasingende')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->required()
                            ->after('lease_start'),
                    ]),

                Section::make('Kosten')
                    ->columns(3)
                    // Sichtbarkeit UND Speicherung an das Recht gebunden: ohne
                    // dehydrated() wuerde ein Speichern ohne Kostenrecht die
                    // vorhandenen Betraege auf null setzen.
                    ->visible(fn (): bool => auth()->user()?->can('viewCosts') ?? false)
                    ->schema([
                        TextInput::make('monthly_lease_rate')
                            ->label('Leasingrate (monatlich)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->dehydrated(fn (): bool => auth()->user()?->can('viewCosts') ?? false),
                        TextInput::make('annual_vehicle_tax')
                            ->label('Kfz-Steuer (jährlich)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->dehydrated(fn (): bool => auth()->user()?->can('viewCosts') ?? false),
                        TextInput::make('annual_insurance')
                            ->label('Versicherung (jährlich)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->dehydrated(fn (): bool => auth()->user()?->can('viewCosts') ?? false),
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
