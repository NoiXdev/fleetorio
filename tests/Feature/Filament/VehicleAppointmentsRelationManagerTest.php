<?php

use App\Enums\AppointmentType;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\RelationManagers\VehicleAppointmentsRelationManager;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAppointment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

it('shows the appointments relation manager on the vehicle edit page', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $vehicle = Vehicle::factory()->create();

    livewire(EditVehicle::class, ['record' => $vehicle->id])
        ->assertOk()
        ->assertSeeLivewire(VehicleAppointmentsRelationManager::class);
});

it('lists appointments sorted by date descending', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $vehicle = Vehicle::factory()->create();
    $oldest = VehicleAppointment::factory()->for($vehicle)->create(['date' => today()->subYear()]);
    $newest = VehicleAppointment::factory()->for($vehicle)->create(['date' => today()]);
    $middle = VehicleAppointment::factory()->for($vehicle)->create(['date' => today()->subMonths(3)]);

    livewire(VehicleAppointmentsRelationManager::class, [
        'ownerRecord' => $vehicle,
        'pageClass' => EditVehicle::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$newest, $middle, $oldest], inOrder: true);
});

it('creates an appointment as an admin', function () {
    actingAs(User::factory()->admin()->withCostAccess()->withTwoFactor()->create());

    $vehicle = Vehicle::factory()->create();

    livewire(VehicleAppointmentsRelationManager::class, [
        'ownerRecord' => $vehicle,
        'pageClass' => EditVehicle::class,
    ])
        ->callTableAction(CreateAction::class, data: [
            'type' => AppointmentType::Inspection->value,
            'date' => '2026-05-01',
            'mileage' => 42000,
            'cost' => 199.90,
            'notes' => 'HU bestanden',
        ])
        ->assertHasNoTableActionErrors();

    assertDatabaseHas(VehicleAppointment::class, [
        'vehicle_id' => $vehicle->id,
        'type' => AppointmentType::Inspection->value,
        'mileage' => 42000,
    ]);
});

describe('cost visibility', function () {
    it('shows the cost column to a user with the permission', function () {
        actingAs(User::factory()->admin()->withCostAccess()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();
        VehicleAppointment::factory()->for($vehicle)->create();

        livewire(VehicleAppointmentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])->assertTableColumnVisible('cost');
    });

    it('hides the cost column from a user without the permission', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create(['can_view_costs' => false]));

        $vehicle = Vehicle::factory()->create();
        VehicleAppointment::factory()->for($vehicle)->create();

        livewire(VehicleAppointmentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])->assertTableColumnHidden('cost');
    });

    it('hides the cost field on the appointment form from a user without the permission', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create(['can_view_costs' => false]));

        $vehicle = Vehicle::factory()->create();

        livewire(VehicleAppointmentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])
            ->mountTableAction(CreateAction::class)
            ->assertFormFieldHidden('cost');
    });

    it('keeps an existing appointment cost when a user without permission saves it', function () {
        $vehicle = Vehicle::factory()->create();
        $appointment = VehicleAppointment::factory()->for($vehicle)->create(['cost' => 199.90]);

        actingAs(User::factory()->admin()->withTwoFactor()->create(['can_view_costs' => false]));

        livewire(VehicleAppointmentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])
            ->callTableAction(EditAction::class, $appointment, data: [
                'type' => AppointmentType::Workshop->value,
                'date' => $appointment->date->format('Y-m-d'),
                'notes' => 'geaendert',
            ])
            ->assertHasNoTableActionErrors();

        expect((float) $appointment->refresh()->cost)->toBe(199.90);
    });

    it('ignores a hostile write attempt on the cost field from a user without permission', function () {
        $vehicle = Vehicle::factory()->create();
        $appointment = VehicleAppointment::factory()->for($vehicle)->create(['cost' => 199.90]);

        actingAs(User::factory()->admin()->withTwoFactor()->create(['can_view_costs' => false]));

        livewire(VehicleAppointmentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])
            ->callTableAction(EditAction::class, $appointment, data: [
                'type' => $appointment->type->value,
                'date' => $appointment->date->format('Y-m-d'),
                'cost' => 999,
            ])
            ->assertHasNoTableActionErrors();

        expect((float) $appointment->refresh()->cost)->toBe(199.90);
    });
});

describe('write access is restricted to admins', function () {
    it('hides the write actions from a viewer', function () {
        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();
        $appointment = VehicleAppointment::factory()->for($vehicle)->create();

        livewire(VehicleAppointmentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])
            ->assertTableActionHidden(CreateAction::class)
            ->assertTableActionHidden(EditAction::class, $appointment)
            ->assertTableActionHidden(DeleteAction::class, $appointment);
    });

    // Filaments eigener callTableAction()-Testhelfer weigert sich bereits,
    // eine unsichtbare Action ueberhaupt aufzurufen (assertActionVisible()
    // vorab) - das wuerde nur die UI-Sichtbarkeit erneut pruefen, nicht die
    // eigentliche Autorisierung. Die drei folgenden Tests rufen daher direkt
    // die Livewire-Methode mountTableAction() auf (ohne den Sichtbarkeits-
    // Vorabcheck) und belegen, dass die echte Aktion (InteractsWithActions::
    // mountAction(), siehe isDisabled()/isAuthorizedOrNotHiddenWhenUnauthorized())
    // die Ausfuehrung verweigert: die Action bleibt unmounted, und es
    // aendert sich nichts in der Datenbank.

    it('refuses a direct create attempt by a viewer, not just a hidden button', function () {
        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();

        $component = livewire(VehicleAppointmentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])->mountTableAction(CreateAction::class);

        expect($component->instance()->mountedActions)->toBeEmpty();

        assertDatabaseMissing(VehicleAppointment::class, ['vehicle_id' => $vehicle->id]);
    });

    it('refuses a direct edit attempt by a viewer, not just a hidden button', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());
        $vehicle = Vehicle::factory()->create();
        $appointment = VehicleAppointment::factory()->for($vehicle)->create(['notes' => 'original']);

        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $component = livewire(VehicleAppointmentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])->mountTableAction(EditAction::class, $appointment);

        expect($component->instance()->mountedActions)->toBeEmpty();

        expect($appointment->refresh()->notes)->toBe('original');
    });

    it('refuses a direct delete attempt by a viewer, not just a hidden button', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());
        $vehicle = Vehicle::factory()->create();
        $appointment = VehicleAppointment::factory()->for($vehicle)->create();

        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $component = livewire(VehicleAppointmentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => EditVehicle::class,
        ])->mountTableAction(DeleteAction::class, $appointment);

        expect($component->instance()->mountedActions)->toBeEmpty();

        assertDatabaseHas(VehicleAppointment::class, ['id' => $appointment->id]);
    });

    it('denies a viewer direct http access to the vehicle edit page entirely', function () {
        // Zusaetzliche Huerde vor der oben getesteten: ein Viewer erreicht die
        // Fahrzeug-Bearbeitungsseite (und damit den Relation Manager) im
        // normalen Seitenaufruf ueberhaupt nicht (VehiclePolicy::update).
        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();

        $this->get("/admin/vehicles/{$vehicle->id}/edit")->assertForbidden();
    });
});
