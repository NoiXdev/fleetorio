<?php

use App\Filament\Resources\VehicleCosts\Pages\ListVehicleCosts;
use App\Filament\Resources\VehicleCosts\VehicleCostResource;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('is reachable for a user with cost permission', function () {
    actingAs(User::factory()->admin()->withCostAccess()->withTwoFactor()->create());

    $vehicles = Vehicle::factory()->count(3)->create();

    livewire(ListVehicleCosts::class)
        ->assertOk()
        ->assertCanSeeTableRecords($vehicles);
});

it('denies access without cost permission', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create(['can_view_costs' => false]));

    expect(VehicleCostResource::canViewAny())->toBeFalse();

    $this->get('/admin/costs')->assertForbidden();
});

it('denies access to a viewer without cost permission', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    expect(VehicleCostResource::canViewAny())->toBeFalse();
});

it('grants access to a viewer who has cost permission', function () {
    // Kostenrecht ist bewusst unabhaengig von der Rolle.
    actingAs(User::factory()->viewer()->withCostAccess()->withTwoFactor()->create());

    expect(VehicleCostResource::canViewAny())->toBeTrue();

    livewire(ListVehicleCosts::class)->assertOk();
});

it('never allows writing through the cost resource', function () {
    $vehicle = Vehicle::factory()->create();

    actingAs(User::factory()->admin()->withCostAccess()->withTwoFactor()->create());

    expect(VehicleCostResource::canCreate())->toBeFalse()
        ->and(VehicleCostResource::canEdit($vehicle))->toBeFalse()
        ->and(VehicleCostResource::canDelete($vehicle))->toBeFalse();
});

it('shows active vehicles by default', function () {
    actingAs(User::factory()->admin()->withCostAccess()->withTwoFactor()->create());

    $active = Vehicle::factory()->create();
    $retired = Vehicle::factory()->retired()->create();

    livewire(ListVehicleCosts::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$retired]);
});
