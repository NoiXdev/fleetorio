<?php

use App\Models\Dealership;
use App\Models\Employee;
use App\Models\FuelCard;
use App\Models\LeasingCompany;
use App\Models\User;
use App\Models\Vehicle;

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
