<?php

use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('lets a user with the cost permission save the gross list price', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create(['can_view_costs' => true]));

    $vehicle = Vehicle::factory()->create(['gross_list_price' => null]);

    livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->fillForm(['gross_list_price' => 42500.00])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((float) $vehicle->fresh()->gross_list_price)->toBe(42500.00);
});

// Dieselbe Absicherung wie fuer die uebrigen Kostenfelder: Das Feld liegt in
// der versteckten Section, deren Zustand beim Speichern verworfen wird. Ein
// mitgeschickter Wert darf nicht durchkommen.
it('discards a gross list price submitted by a user without the cost permission', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create(['can_view_costs' => false]));

    $vehicle = Vehicle::factory()->create(['gross_list_price' => 30000.00]);

    livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->fillForm(['gross_list_price' => 1.00])
        ->call('save');

    expect((float) $vehicle->fresh()->gross_list_price)->toBe(30000.00);
});

it('does not include the gross list price in the annual cost total', function () {
    // Ein Listenpreis ist keine laufende Position - die Jahressumme muss
    // dieselbe bleiben wie ohne den Wert.
    $vehicle = Vehicle::factory()->create([
        'monthly_lease_rate' => 500,
        'annual_vehicle_tax' => 200,
        'annual_insurance' => 300,
        'gross_list_price' => 45000,
    ]);

    expect($vehicle->annual_total_cost)->toBe((float) (500 * 12 + 200 + 300));
});
