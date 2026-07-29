<?php

use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
});

it('lists employees', function () {
    $employees = Employee::factory()->count(3)->create();

    livewire(ListEmployees::class)
        ->assertOk()
        ->assertCanSeeTableRecords($employees);
});

it('creates an employee', function () {
    livewire(\App\Filament\Resources\Employees\Pages\CreateEmployee::class)
        ->fillForm([
            'first_name' => 'Anna',
            'last_name' => 'Beispiel',
            'department' => 'Vertrieb',
            'email' => 'anna@example.com',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Employee::class, ['last_name' => 'Beispiel']);
});

it('requires first and last name', function () {
    livewire(\App\Filament\Resources\Employees\Pages\CreateEmployee::class)
        ->fillForm(['first_name' => null, 'last_name' => null])
        ->call('create')
        ->assertHasFormErrors(['first_name' => 'required', 'last_name' => 'required']);
});

it('refuses to delete an employee who still has a vehicle', function () {
    $employee = Employee::factory()->create();
    Vehicle::factory()->for($employee)->create(['license_plate' => 'M-AB 1234']);

    livewire(EditEmployee::class, ['record' => $employee->id])
        ->callAction(GuardedDeleteAction::class)
        ->assertNotified();

    // Entscheidend: der Datensatz existiert noch.
    assertDatabaseHas(Employee::class, ['id' => $employee->id]);
});

it('deletes an employee without vehicles', function () {
    $employee = Employee::factory()->create();

    livewire(EditEmployee::class, ['record' => $employee->id])
        ->callAction(GuardedDeleteAction::class);

    expect(Employee::find($employee->id))->toBeNull();
});

it('hides write actions from viewers', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());
    Employee::factory()->create();

    livewire(ListEmployees::class)
        ->assertOk()
        ->assertActionHidden(\Filament\Actions\CreateAction::class);
});

it('denies a viewer direct http access to the create-employee page', function () {
    // Nicht nur die Navigation ausgeblendet, sondern auch per direktem Request
    // gesperrt (siehe VehiclePolicy-Aequivalent EmployeePolicy::create).
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $this->get('/admin/employees/create')->assertForbidden();
});
