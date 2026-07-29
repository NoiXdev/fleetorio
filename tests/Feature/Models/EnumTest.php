<?php

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
