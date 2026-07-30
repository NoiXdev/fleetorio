<?php

use App\Models\Dealership;
use App\Models\Employee;
use App\Models\FuelCard;
use App\Models\LeasingCompany;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAppointment;
use App\Models\VehicleDocument;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->viewer = User::factory()->viewer()->create();
});

it('lets everyone read the fleet', function () {
    $vehicle = Vehicle::factory()->create();

    expect($this->viewer->can('viewAny', Vehicle::class))->toBeTrue()
        ->and($this->viewer->can('view', $vehicle))->toBeTrue()
        ->and($this->admin->can('viewAny', Vehicle::class))->toBeTrue();
});

it('lets only admins write vehicles', function () {
    $vehicle = Vehicle::factory()->create();

    expect($this->admin->can('create', Vehicle::class))->toBeTrue()
        ->and($this->admin->can('update', $vehicle))->toBeTrue()
        ->and($this->admin->can('delete', $vehicle))->toBeTrue()
        ->and($this->viewer->can('create', Vehicle::class))->toBeFalse()
        ->and($this->viewer->can('update', $vehicle))->toBeFalse()
        ->and($this->viewer->can('delete', $vehicle))->toBeFalse();
});

it('blocks bulk deletion for viewers', function () {
    expect($this->admin->can('deleteAny', Vehicle::class))->toBeTrue()
        ->and($this->viewer->can('deleteAny', Vehicle::class))->toBeFalse();
});

it('applies the same rule to master data', function (string $model) {
    $record = $model::factory()->create();

    expect($this->viewer->can('viewAny', $model))->toBeTrue()
        ->and($this->viewer->can('update', $record))->toBeFalse()
        ->and($this->admin->can('update', $record))->toBeTrue();
})->with([
    Employee::class,
    FuelCard::class,
    LeasingCompany::class,
    Dealership::class,
]);

it('lets everyone read appointments but only admins write them', function () {
    // Nicht Teil der generischen Stammdaten-with()-Liste oben, weil
    // VehicleAppointment::factory() ohne ein zugehoeriges Fahrzeug nicht
    // erzeugt werden kann (vehicle_id ist Pflicht).
    $appointment = VehicleAppointment::factory()->for(Vehicle::factory())->create();

    expect($this->viewer->can('viewAny', VehicleAppointment::class))->toBeTrue()
        ->and($this->viewer->can('view', $appointment))->toBeTrue()
        ->and($this->viewer->can('create', VehicleAppointment::class))->toBeFalse()
        ->and($this->viewer->can('update', $appointment))->toBeFalse()
        ->and($this->viewer->can('delete', $appointment))->toBeFalse()
        ->and($this->admin->can('create', VehicleAppointment::class))->toBeTrue()
        ->and($this->admin->can('update', $appointment))->toBeTrue()
        ->and($this->admin->can('delete', $appointment))->toBeTrue();
});

it('lets everyone read documents but only admins write them', function () {
    // Der Kern der Aufgabe: ein Fahrzeugschein enthaelt Fahrgestellnummer und
    // Halterdaten - Hochladen/Aendern/Loeschen bleibt Administratoren
    // vorbehalten, Lesen/Herunterladen jedem angemeldeten Benutzer.
    $document = VehicleDocument::factory()->for(Vehicle::factory())->create();

    expect($this->viewer->can('viewAny', VehicleDocument::class))->toBeTrue()
        ->and($this->viewer->can('view', $document))->toBeTrue()
        ->and($this->viewer->can('create', VehicleDocument::class))->toBeFalse()
        ->and($this->viewer->can('update', $document))->toBeFalse()
        ->and($this->viewer->can('delete', $document))->toBeFalse()
        ->and($this->admin->can('create', VehicleDocument::class))->toBeTrue()
        ->and($this->admin->can('update', $document))->toBeTrue()
        ->and($this->admin->can('delete', $document))->toBeTrue();
});

it('restricts user administration to admins entirely', function () {
    expect($this->admin->can('viewAny', User::class))->toBeTrue()
        ->and($this->admin->can('update', $this->viewer))->toBeTrue()
        ->and($this->viewer->can('viewAny', User::class))->toBeFalse()
        ->and($this->viewer->can('view', $this->admin))->toBeFalse()
        ->and($this->viewer->can('update', $this->admin))->toBeFalse()
        ->and($this->viewer->can('create', User::class))->toBeFalse();
});

it('stops an admin from deleting their own account', function () {
    $other = User::factory()->admin()->create();

    expect($this->admin->can('delete', $this->admin))->toBeFalse()
        ->and($this->admin->can('delete', $other))->toBeTrue();
});
