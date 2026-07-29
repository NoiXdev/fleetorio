<?php

use App\Enums\VehicleStatus;
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

/**
 * Der fachliche Kern der Task: die Summenzeile muss dem sichtbaren, gefilterten
 * Datensatz folgen, nicht der gesamten Tabelle. Das prueft insbesondere die
 * Vehicle::query()->setQuery($query)-Bruecke in VehicleCostsTable, die vom
 * Task-Brief abweicht (siehe Report) - bricht diese Bruecke durch ein
 * Filament-Update, waere eine falsche, aber plausibel aussehende Zahl der
 * schlimmste Fehlermodus fuer eine Kostenauswertung.
 */
it('summarizes annual total cost only for vehicles matching the current status filter', function () {
    actingAs(User::factory()->admin()->withCostAccess()->withTwoFactor()->create());

    Vehicle::factory()->create([
        'monthly_lease_rate' => 100.00,
        'annual_vehicle_tax' => 50.00,
        'annual_insurance' => 25.00,
    ]);
    // (100 * 12 + 50 + 25) = 1275

    Vehicle::factory()->retired()->create([
        'monthly_lease_rate' => 9000.00,
        'annual_vehicle_tax' => 9000.00,
        'annual_insurance' => 9000.00,
    ]);
    // (9000 * 12 + 9000 + 9000) = 126000 - auffaellig hoch, damit eine
    // versehentlich mitgezaehlte Zeile nicht unbemerkt bliebe.

    $component = livewire(ListVehicleCosts::class);

    // Standardfilter ist Active: die Summe darf den ausgemusterten Betrag
    // nicht enthalten - weder in der abgeleiteten Summarizer-Spalte
    // (annual_total_cost, laeuft ueber setQuery()) noch in einer regulaeren
    // Sum::make()-Spalte als Gegenprobe.
    $component
        ->assertTableColumnSummarySet('annual_total_cost', 'total_sum', 1275.0)
        ->assertTableColumnSummarySet('monthly_lease_rate', 'lease_sum', 100.0);

    // Nach Umschalten auf Retired muss die Summe genau dem ausgemusterten
    // Fahrzeug entsprechen.
    $component
        ->filterTable('status', VehicleStatus::Retired->value)
        ->assertTableColumnSummarySet('annual_total_cost', 'total_sum', 126000.0)
        ->assertTableColumnSummarySet('monthly_lease_rate', 'lease_sum', 9000.0);
});

/**
 * Der Slug 'costs' ist die Stelle, an der beide Vehicle-Resources kollidieren
 * koennten. Ohne diesen Test faellt eine Regression erst im Betrieb auf.
 */
it('keeps both vehicle resources reachable at their own routes', function () {
    actingAs(User::factory()->admin()->withCostAccess()->withTwoFactor()->create());

    Vehicle::factory()->count(2)->create();

    $this->get('/admin/vehicles')->assertOk();
    $this->get('/admin/costs')->assertOk();
});
