<?php

use App\Enums\AppointmentType;
use App\Enums\FuelCardProvider;
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

it('provides labels and colours for vehicle status', function () {
    expect(VehicleStatus::Active->getLabel())->toBe('Aktiv')
        ->and(VehicleStatus::Retired->getLabel())->toBe('Ausgemustert')
        ->and(VehicleStatus::Active->getColor())->toBe('success')
        ->and(VehicleStatus::Retired->getColor())->toBe('gray');
});

it('provides labels for fuel card providers', function () {
    expect(FuelCardProvider::Dkv->getLabel())->toBe('DKV')
        ->and(FuelCardProvider::Elli->getLabel())->toBe('Elli')
        ->and(FuelCardProvider::Other->getLabel())->toBe('Sonstige');
});

it('provides labels for user roles', function () {
    expect(UserRole::Admin->getLabel())->toBe('Administrator')
        ->and(UserRole::Viewer->getLabel())->toBe('Nur-Lesen');
});

it('provides German labels for appointment types', function () {
    expect(AppointmentType::Service->getLabel())->toBe('Service')
        ->and(AppointmentType::Workshop->getLabel())->toBe('Werkstatt')
        ->and(AppointmentType::Inspection->getLabel())->toBe('Hauptuntersuchung');
});

it('gives appointment types colors distinct from danger and primary', function () {
    // 'danger' ist in diesem Panel auf Rose verschoben, 'primary' ist das
    // Markenrot (siehe AdminPanelProvider) - beide sind reserviert und
    // duerfen fuer Termin-Badges nicht wiederverwendet werden.
    $colors = array_map(fn (AppointmentType $type): string => $type->getColor(), AppointmentType::cases());

    expect(array_intersect($colors, ['danger', 'primary']))->toBeEmpty()
        ->and(array_unique($colors))->toHaveCount(3);
});
