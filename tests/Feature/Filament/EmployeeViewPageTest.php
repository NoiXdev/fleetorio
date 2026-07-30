<?php

use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\Pages\ViewEmployee;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Actions\EditAction;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('lets a read-only user reach the employee view page', function () {
    // Der eigentliche Gewinn dieser Aufgabe: bisher endete dieser Aufruf mit
    // 403, weil es nur die Bearbeitungsseite gab (EmployeePolicy::update).
    // EmployeePolicy::view() gibt schon immer true zurueck.
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $employee = Employee::factory()->create();

    $this->get("/admin/employees/{$employee->id}")->assertOk();

    livewire(ViewEmployee::class, ['record' => $employee->id])
        ->assertOk();
});

it('sends a table row click to the view page automatically once it is registered', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $employee = Employee::factory()->create();

    $table = livewire(ListEmployees::class)->instance()->getTable();

    expect($table->getRecordUrl($employee))
        ->toBe(ViewEmployee::getUrl(['record' => $employee]));
});

it('shows the email address on the view page, which is otherwise unreachable for a read-only user', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $employee = Employee::factory()->create(['email' => 'anna.beispiel@example.com']);

    livewire(ViewEmployee::class, ['record' => $employee->id])
        ->assertOk()
        ->assertSee('anna.beispiel@example.com');
});

it('shows the vehicle count on the view page', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $employee = Employee::factory()->create();
    Vehicle::factory()->for($employee)->count(3)->create();

    livewire(ViewEmployee::class, ['record' => $employee->id])
        ->assertOk()
        ->assertSeeInOrder(['Fahrzeuge', '3']);
});

it('renders the view page without crashing when every optional field is empty', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $employee = Employee::factory()->create([
        'department' => null,
        'email' => null,
    ]);

    livewire(ViewEmployee::class, ['record' => $employee->id])
        ->assertOk()
        ->assertSee('—');
});

describe('the edit button', function () {
    it('is hidden from a read-only user', function () {
        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $employee = Employee::factory()->create();

        livewire(ViewEmployee::class, ['record' => $employee->id])
            ->assertActionHidden(EditAction::class);
    });

    it('leads an admin to the dedicated edit page, not a modal', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $employee = Employee::factory()->create();

        livewire(ViewEmployee::class, ['record' => $employee->id])
            ->assertActionVisible(EditAction::class)
            ->assertActionHasUrl(EditAction::class, EditEmployee::getUrl(['record' => $employee->id]));
    });
});
