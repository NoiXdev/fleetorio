<?php

use App\Enums\AppointmentType;
use App\Enums\FuelType;
use App\Enums\UserRole;
use App\Enums\VehicleStatus;

it('provides German labels for fuel types', function () {
    expect(FuelType::Diesel->getLabel())->toBe('Diesel')
        ->and(FuelType::PluginHybrid->getLabel())->toBe('Plug-in-Hybrid')
        ->and(FuelType::Electric->getLabel())->toBe('Elektro')
        ->and(FuelType::Other->getLabel())->toBe('Sonstige');
});

it('covers every fuel type from the spec', function () {
    expect(FuelType::cases())->toHaveCount(6);
});

it('provides an icon for every fuel type', function (FuelType $fuelType) {
    expect($fuelType->getIcon())->not->toBeNull();
})->with(FuelType::cases());

it('provides labels and colours for vehicle status', function () {
    expect(VehicleStatus::Active->getLabel())->toBe('Aktiv')
        ->and(VehicleStatus::Retired->getLabel())->toBe('Ausgemustert')
        ->and(VehicleStatus::Active->getColor())->toBe('success')
        ->and(VehicleStatus::Retired->getColor())->toBe('gray');
});

it('provides labels for user roles', function () {
    expect(UserRole::Admin->getLabel())->toBe('Administrator')
        ->and(UserRole::Viewer->getLabel())->toBe('Nur-Lesen');
});

it('provides German labels for appointment types', function () {
    expect(AppointmentType::Service->getLabel())->toBe('Service')
        ->and(AppointmentType::Workshop->getLabel())->toBe('Werkstatt')
        ->and(AppointmentType::Inspection->getLabel())->toBe('Hauptuntersuchung')
        ->and(AppointmentType::Accident->getLabel())->toBe('Unfall')
        ->and(AppointmentType::Damage->getLabel())->toBe('Schaden');
});

// 'primary' ist das Markenrot (siehe AdminPanelProvider) und bleibt fuer
// Historien-Badges reserviert. 'danger' war ursprunglich ebenfalls tabu -
// mit Aufnahme des Unfalls hat es genau dort seinen Platz gefunden, weil
// ein Unfall hervorstechen soll (siehe AppointmentType::getColor()).
it('gives every appointment type a distinct colour that is not primary', function () {
    $colors = array_map(fn (AppointmentType $type): string => $type->getColor(), AppointmentType::cases());

    expect(array_intersect($colors, ['primary']))->toBeEmpty()
        ->and($colors)->toHaveCount(count(array_unique($colors)));
});
