<?php

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use App\Filament\Actions\ActivateAction;
use App\Filament\Actions\DeactivateAction;
use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\Pages\ListVehicles;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
});

describe('which actions a row offers', function () {
    it('offers deactivating but not deleting while a record is active', function () {
        $employee = Employee::factory()->create(['active' => true]);

        livewire(ListEmployees::class)
            ->assertTableActionVisible(DeactivateAction::class, $employee)
            ->assertTableActionHidden(GuardedDeleteAction::class, $employee)
            ->assertTableActionHidden(ActivateAction::class, $employee);
    });

    it('offers deleting and activating once a record is inactive', function () {
        $employee = Employee::factory()->create(['active' => false]);

        livewire(ListEmployees::class)
            ->filterTable('active', false)
            ->assertTableActionVisible(GuardedDeleteAction::class, $employee)
            ->assertTableActionVisible(ActivateAction::class, $employee)
            ->assertTableActionHidden(DeactivateAction::class, $employee);
    });
});

describe('the state transitions', function () {
    // Der Kern der Anforderung: Ausscheiden aus der Firma darf nicht daran
    // scheitern, dass noch ein Auto zugeordnet ist. Die Zuordnung bleibt.
    it('deactivates an employee who still has a vehicle, keeping the assignment', function () {
        $employee = Employee::factory()->create(['active' => true]);
        $vehicle = Vehicle::factory()->for($employee)->create();

        livewire(ListEmployees::class)
            ->callTableAction(DeactivateAction::class, $employee);

        assertDatabaseHas(Employee::class, ['id' => $employee->id, 'active' => false]);
        expect($vehicle->fresh()->employee_id)->toBe($employee->id);
    });

    it('activates an inactive employee again', function () {
        $employee = Employee::factory()->create(['active' => false]);

        livewire(ListEmployees::class)
            ->filterTable('active', false)
            ->callTableAction(ActivateAction::class, $employee);

        assertDatabaseHas(Employee::class, ['id' => $employee->id, 'active' => true]);
    });

    it('retires a vehicle rather than deleting it', function () {
        $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);

        livewire(ListVehicles::class)
            ->callTableAction(DeactivateAction::class, $vehicle);

        expect($vehicle->fresh()->status)->toBe(VehicleStatus::Retired);
    });
});

describe('deleting', function () {
    // Gegen MariaDB, damit der echte Fremdschluessel (restrictOnDelete)
    // greift und nicht eine Nachbildung.
    it('refuses to delete an inactive employee who is still assigned to a vehicle', function () {
        $employee = Employee::factory()->create(['active' => false]);
        Vehicle::factory()->for($employee)->create();

        livewire(ListEmployees::class)
            ->filterTable('active', false)
            ->callTableAction(GuardedDeleteAction::class, $employee);

        assertDatabaseHas(Employee::class, ['id' => $employee->id]);
    });

    it('deletes an inactive employee who is no longer assigned', function () {
        $employee = Employee::factory()->create(['active' => false]);

        livewire(ListEmployees::class)
            ->filterTable('active', false)
            ->callTableAction(GuardedDeleteAction::class, $employee);

        expect(Employee::find($employee->id))->toBeNull();
    });

    // Die Fahrzeugliste bietet gar kein Loeschen an - beim Fahrzeug haengt es
    // am Formularfuss der Bearbeitungsseite. Ohne diese Absicherung waere die
    // Regel ausgerechnet dort umgehbar, wo man ein Fahrzeug wirklich loescht.
    it('hides deleting on the vehicle edit page while the vehicle is in service', function () {
        $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);

        livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
            ->assertActionHidden('delete');
    });

    it('shows deleting on the vehicle edit page once the vehicle is retired', function () {
        $vehicle = Vehicle::factory()->retired()->create();

        livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
            ->assertActionVisible('delete');
    });
});

describe('unique values held by an inactive record', function () {
    // Der Unique-Index bleibt bewusst wie er ist - sonst stuende dasselbe
    // Kennzeichen zweimal in der Tabelle. Die Meldung muss dafuer erklaeren,
    // warum der belegende Eintrag nicht zu finden ist.
    it('explains that a retired vehicle may be holding the licence plate', function () {
        Vehicle::factory()->retired()->create(['license_plate' => 'M-AB 123']);

        $component = livewire(CreateVehicle::class)
            ->fillForm([
                'brand' => 'VW',
                'model' => 'Golf',
                'license_plate' => 'M-AB 123',
                'fuel_type' => FuelType::Diesel->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['license_plate']);

        expect($component->instance()->getErrorBag()->first('data.license_plate'))
            ->toContain('ausgemusterten');
    });
});

describe('the list default', function () {
    it('hides inactive records by default but shows them when filtered', function () {
        $active = Employee::factory()->create(['active' => true]);
        $inactive = Employee::factory()->create(['active' => false]);

        livewire(ListEmployees::class)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive])
            ->filterTable('active', false)
            ->assertCanSeeTableRecords([$inactive]);
    });
});
