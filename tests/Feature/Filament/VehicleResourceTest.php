<?php

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\Pages\ListVehicles;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

it('shows the vehicle list without crashing when a vehicle has no lease end', function () {
    // Die Spalte "Leasingende" berechnet aus lease_end die Resttage und daraus
    // eine Farbe; bei null darf das nicht (mehr) crashen.
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $vehicle = Vehicle::factory()->withoutLeaseDates()->create();

    livewire(ListVehicles::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$vehicle])
        ->assertSee('—');
});

it('lists active vehicles by default and hides retired ones', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $active = Vehicle::factory()->count(2)->create();
    $retired = Vehicle::factory()->retired()->create();

    livewire(ListVehicles::class)
        ->assertOk()
        ->assertCanSeeTableRecords($active)
        ->assertCanNotSeeTableRecords([$retired]);
});

it('shows retired vehicles when the status filter is changed', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $retired = Vehicle::factory()->retired()->create();

    livewire(ListVehicles::class)
        ->filterTable('status', VehicleStatus::Retired->value)
        ->assertCanSeeTableRecords([$retired]);
});

it('searches by licence plate', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $wanted = Vehicle::factory()->create(['license_plate' => 'M-AB 1234']);
    $other = Vehicle::factory()->create(['license_plate' => 'M-ZZ 9999']);

    livewire(ListVehicles::class)
        ->searchTable('M-AB')
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$other]);
});

it('searches by the driver last name', function () {
    // Genau die Zeile, die im Review korrigiert wurde (searchable(['first_name',
    // 'last_name']) statt der fehlerhaften Tabellen-qualifizierten Fassung), war
    // bisher ungetestet.
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $driver = Employee::factory()->create(['last_name' => 'Musterfrau']);
    $wanted = Vehicle::factory()->create(['employee_id' => $driver->id]);
    $other = Vehicle::factory()->create(['employee_id' => null]);

    livewire(ListVehicles::class)
        ->searchTable('Musterfrau')
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$other]);
});

it('filters by fuel type', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $electric = Vehicle::factory()->create(['fuel_type' => FuelType::Electric]);
    $diesel = Vehicle::factory()->create(['fuel_type' => FuelType::Diesel]);

    livewire(ListVehicles::class)
        ->filterTable('fuel_type', FuelType::Electric->value)
        ->assertCanSeeTableRecords([$electric])
        ->assertCanNotSeeTableRecords([$diesel]);
});

it('creates a vehicle', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $employee = Employee::factory()->create();

    livewire(CreateVehicle::class)
        ->fillForm([
            'brand' => 'VW',
            'model' => 'Passat',
            'license_plate' => 'M-AB 1234',
            'fuel_type' => FuelType::Diesel->value,
            'employee_id' => $employee->id,
            'lease_start' => '2026-01-01',
            'lease_end' => '2029-01-01',
            'monthly_lease_rate' => 450,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Vehicle::class, [
        'license_plate' => 'M-AB 1234',
        'employee_id' => $employee->id,
    ]);
});

it('rejects a duplicate licence plate', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    Vehicle::factory()->create(['license_plate' => 'M-AB 1234']);

    livewire(CreateVehicle::class)
        ->fillForm([
            'brand' => 'VW',
            'model' => 'Golf',
            'license_plate' => 'M-AB 1234',
            'fuel_type' => FuelType::Diesel->value,
            'lease_start' => '2026-01-01',
            'lease_end' => '2029-01-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['license_plate' => 'unique']);
});

it('shows a short placeholder instead of Filaments default long one on the selects without a default value', function () {
    // Filaments deutsche Übersetzung setzt fuer Select-Felder ohne eigenen
    // Platzhalter den Text "Wählen Sie eine Option" (siehe vendor/filament/
    // forms/resources/lang/de/components.php, Schluessel select.placeholder).
    // Das betrifft hier nicht nur den Leasinggeber, sondern auch Mitarbeiter,
    // Tankkarte und Antriebsart - alle vier haben keinen Standardwert.
    //
    // Status hat einen Standardwert (VehicleStatus::Active) und zeigt daher
    // nie sichtbar einen Platzhalter; er bleibt bewusst unangetastet - sein
    // interner JS-Konfigurationswert traegt also weiterhin den langen Text
    // (nie gerendert), weshalb dieser Test die restliche Zahl der Treffer
    // prueft statt pauschal auf Abwesenheit.
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $html = livewire(CreateVehicle::class)->html();

    expect(substr_count($html, 'Wählen Sie eine Option'))->toBe(1)
        ->and(substr_count($html, 'Auswählen'))->toBe(4);
});

it('renders lease start and end as native date inputs so they can be typed', function () {
    // Filaments eigener Picker (native(false)) rendert das sichtbare Feld als
    // readonly-Input in einem Button (siehe vendor/filament/forms/src/
    // Components/DateTimePicker.php) - eine Tastatureingabe ist dort gar
    // nicht moeglich. Das native Browser-Datumsfeld ist es hingegen.
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $html = livewire(CreateVehicle::class)->html();

    expect($html)->toContain('id="form.lease_start"')
        ->toContain('id="form.lease_end"')
        ->not->toContain('fi-fo-date-time-picker-display-text-input');
});

it('rejects a lease end before the lease start', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    livewire(CreateVehicle::class)
        ->fillForm([
            'brand' => 'VW',
            'model' => 'Golf',
            'license_plate' => 'M-XY 4321',
            'fuel_type' => FuelType::Diesel->value,
            'lease_start' => '2029-01-01',
            'lease_end' => '2026-01-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['lease_end']);
});

describe('optional lease dates', function () {
    // Leasingstart und Leasingende sind seit diesem Wunsch keine Pflichtfelder
    // mehr. ->after('lease_start') muss weiterhin greifen, wenn beide Felder
    // gefuellt sind, darf aber nicht stoeren, wenn eines oder beide leer sind.

    it('allows creating a vehicle with neither lease date set', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        livewire(CreateVehicle::class)
            ->fillForm([
                'brand' => 'VW',
                'model' => 'Golf',
                'license_plate' => 'M-LD 0001',
                'fuel_type' => FuelType::Diesel->value,
                'lease_start' => null,
                'lease_end' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        assertDatabaseHas(Vehicle::class, [
            'license_plate' => 'M-LD 0001',
            'lease_start' => null,
            'lease_end' => null,
        ]);
    });

    it('allows creating a vehicle with only a lease start date', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        livewire(CreateVehicle::class)
            ->fillForm([
                'brand' => 'VW',
                'model' => 'Golf',
                'license_plate' => 'M-LD 0002',
                'fuel_type' => FuelType::Diesel->value,
                'lease_start' => '2026-01-01',
                'lease_end' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        assertDatabaseHas(Vehicle::class, [
            'license_plate' => 'M-LD 0002',
            'lease_end' => null,
        ]);
    });

    it('allows creating a vehicle with only a lease end date', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        livewire(CreateVehicle::class)
            ->fillForm([
                'brand' => 'VW',
                'model' => 'Golf',
                'license_plate' => 'M-LD 0003',
                'fuel_type' => FuelType::Diesel->value,
                'lease_start' => null,
                'lease_end' => '2029-01-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        assertDatabaseHas(Vehicle::class, [
            'license_plate' => 'M-LD 0003',
            'lease_start' => null,
        ]);
    });

    it('still rejects a lease end before the lease start when both are set', function () {
        // Deckt denselben Fall wie oben ab, hier bewusst innerhalb der
        // describe-Gruppe zur optionalen Pflicht dokumentiert.
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        livewire(CreateVehicle::class)
            ->fillForm([
                'brand' => 'VW',
                'model' => 'Golf',
                'license_plate' => 'M-LD 0004',
                'fuel_type' => FuelType::Diesel->value,
                'lease_start' => '2029-01-01',
                'lease_end' => '2026-01-01',
            ])
            ->call('create')
            ->assertHasFormErrors(['lease_end']);
    });
});

it('rejects negative amounts', function (string $field) {
    // monthly_lease_rate, annual_vehicle_tax und annual_insurance tragen alle
    // drei minValue(0) (siehe VehicleForm); zuvor war nur das erste Feld
    // getestet.
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    livewire(CreateVehicle::class)
        ->fillForm([
            'brand' => 'VW',
            'model' => 'Golf',
            'license_plate' => 'M-XY 5555',
            'fuel_type' => FuelType::Diesel->value,
            'lease_start' => '2026-01-01',
            'lease_end' => '2029-01-01',
            $field => -50,
        ])
        ->call('create')
        ->assertHasFormErrors([$field]);
})->with(['monthly_lease_rate', 'annual_vehicle_tax', 'annual_insurance']);

describe('cost visibility', function () {
    it('shows cost fields to a user with the permission', function () {
        actingAs(User::factory()->admin()->withCostAccess()->withTwoFactor()->create());

        livewire(CreateVehicle::class)
            ->assertFormFieldVisible('monthly_lease_rate')
            ->assertFormFieldVisible('annual_vehicle_tax')
            ->assertFormFieldVisible('annual_insurance');
    });

    it('hides cost fields from a user without the permission', function () {
        actingAs(User::factory()->admin()->create(['can_view_costs' => false]));

        livewire(CreateVehicle::class)
            ->assertFormFieldHidden('monthly_lease_rate')
            ->assertFormFieldHidden('annual_vehicle_tax')
            ->assertFormFieldHidden('annual_insurance');
    });

    it('keeps existing cost values when a user without permission saves', function () {
        // Sonst würde das Bearbeiten eines Fahrzeugs die Kosten stillschweigend loeschen.
        $vehicle = Vehicle::factory()->create([
            'monthly_lease_rate' => 450.00,
            'annual_vehicle_tax' => 180.00,
            'annual_insurance' => 620.00,
        ]);

        actingAs(User::factory()->admin()->withTwoFactor()->create(['can_view_costs' => false]));

        livewire(EditVehicle::class, ['record' => $vehicle->id])
            ->fillForm(['brand' => 'Audi'])
            ->call('save')
            ->assertHasNoFormErrors();

        $vehicle->refresh();

        expect($vehicle->brand)->toBe('Audi')
            ->and((float) $vehicle->monthly_lease_rate)->toBe(450.00)
            ->and((float) $vehicle->annual_vehicle_tax)->toBe(180.00)
            ->and((float) $vehicle->annual_insurance)->toBe(620.00);
    });

    it('ignores a hostile write attempt on a cost field from a user without permission', function () {
        // Beweist, dass die Autorisierung serverseitig ist: nicht nur ein
        // harmloses Speichern ohne Aenderung, sondern der explizite Versuch,
        // einen neuen Betrag einzutragen, wird ignoriert.
        $vehicle = Vehicle::factory()->create([
            'monthly_lease_rate' => 450.00,
        ]);

        actingAs(User::factory()->admin()->withTwoFactor()->create(['can_view_costs' => false]));

        livewire(EditVehicle::class, ['record' => $vehicle->id])
            ->fillForm(['monthly_lease_rate' => 999])
            ->call('save')
            ->assertHasNoFormErrors();

        expect((float) $vehicle->refresh()->monthly_lease_rate)->toBe(450.00);
    });

    it('never shows cost columns in the vehicle table', function () {
        // Die Fahrzeugliste zeigt grundsaetzlich keine Betraege - dafuer gibt es
        // die Kostenseite. Auch nicht fuer Nutzer mit Kostenrecht.
        actingAs(User::factory()->admin()->withCostAccess()->withTwoFactor()->create());
        Vehicle::factory()->create();

        livewire(ListVehicles::class)
            ->assertOk()
            ->assertTableColumnDoesNotExist('monthly_lease_rate');
    });
});

it('hides write actions from viewers', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());
    Vehicle::factory()->create();

    livewire(ListVehicles::class)
        ->assertOk()
        ->assertActionHidden(CreateAction::class);
});

it('stops a viewer from reaching the create page', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $this->get('/admin/vehicles/create')->assertForbidden();
});

it('does not offer bulk deletion for vehicles', function () {
    // Auftraggeber-Entscheidung: Fahrzeuge werden ausgemustert, nicht in Mengen
    // geloescht. Mit zwei Klicks den halben Fuhrpark samt Kostenhistorie zu
    // verlieren, ist ein zu grosses Risiko fuer einen Bequemlichkeitsgewinn.
    actingAs(User::factory()->admin()->withTwoFactor()->create());
    Vehicle::factory()->create();

    livewire(ListVehicles::class)
        ->assertOk()
        ->assertTableBulkActionDoesNotExist('delete');
});

it('lets an admin delete a single vehicle', function () {
    // Das einzelne Loeschen auf der Edit-Seite bleibt erhalten (nur Admins,
    // siehe VehiclePolicy::delete) und verlangt eine Bestaetigung, die
    // Filament fuer DeleteAction standardmaessig erzwingt.
    $vehicle = Vehicle::factory()->create();

    actingAs(User::factory()->admin()->withTwoFactor()->create());

    livewire(EditVehicle::class, ['record' => $vehicle->id])
        ->assertActionExists(DeleteAction::class)
        ->callAction(DeleteAction::class);

    assertDatabaseMissing(Vehicle::class, ['id' => $vehicle->id]);
});

it('stops a viewer from reaching the edit page at all', function () {
    // EditRecord::authorizeAccess() bricht mit 403 ab, bevor die Seite ueberhaupt
    // mountet (VehiclePolicy::update ist nur fuer Admins wahr) - ein Viewer sieht
    // die einzelne Loeschen-Action also gar nicht erst, weil er die Seite selbst
    // nicht erreicht.
    $vehicle = Vehicle::factory()->create();

    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $this->get("/admin/vehicles/{$vehicle->id}/edit")->assertForbidden();
});
