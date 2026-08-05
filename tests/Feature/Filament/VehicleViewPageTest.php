<?php

use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Resources\Vehicles\Pages\ViewVehicle;
use App\Filament\Resources\Vehicles\RelationManagers\VehicleAppointmentsRelationManager;
use App\Filament\Resources\Vehicles\RelationManagers\VehicleDocumentsRelationManager;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAppointment;
use App\Models\VehicleDocument;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('lets a read-only user reach the vehicle view page', function () {
    // Der eigentliche Gewinn dieser Aufgabe: bisher endete dieser Aufruf mit
    // 403, weil es nur die Bearbeitungsseite gab (VehiclePolicy::update).
    // VehiclePolicy::view() gibt schon immer true zurueck.
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $vehicle = Vehicle::factory()->create();

    $this->get("/admin/vehicles/{$vehicle->id}")->assertOk();

    livewire(ViewVehicle::class, ['record' => $vehicle->id])
        ->assertOk();
});

it('sends a table row click to the view page automatically once it is registered', function () {
    // Filament setzt recordUrl() selbst (Filament\Resources\Pages\ListRecords::
    // table(), Block "if (! $table->hasCustomRecordUrl())"), sobald die
    // Ressource eine 'view'-Seite registriert und canView() zutrifft - kein
    // eigenes ->recordUrl() noetig.
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $vehicle = Vehicle::factory()->create();

    $table = livewire(ListVehicles::class)->instance()->getTable();

    expect($table->getRecordUrl($vehicle))
        ->toBe(ViewVehicle::getUrl(['record' => $vehicle]));
});

it('renders the view page without crashing when every optional field is empty', function () {
    // Realistischster Absturzkandidat nach den letzten Aenderungen: kein
    // Mitarbeiter, keine Tankkarte, kein Leasinggeber, kein Autohaus, keine
    // Leasingdaten, keine HU, keine Kosten, keine Notiz.
    actingAs(User::factory()->admin()->withCostAccess()->withTwoFactor()->create());

    $vehicle = Vehicle::factory()->withoutLeaseDates()->create([
        'image_path' => null,
        'employee_id' => null,
        'fuel_card_id' => null,
        'leasing_company_id' => null,
        'dealership_id' => null,
        'inspection_valid_until' => null,
        'monthly_lease_rate' => null,
        'annual_vehicle_tax' => null,
        'annual_insurance' => null,
        'notes' => null,
    ]);

    livewire(ViewVehicle::class, ['record' => $vehicle->id])
        ->assertOk()
        ->assertSee('—');
});

describe('the edit button', function () {
    it('is hidden from a read-only user', function () {
        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();

        livewire(ViewVehicle::class, ['record' => $vehicle->id])
            ->assertActionHidden(EditAction::class);
    });

    it('leads an admin to the dedicated edit page, not a modal', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();

        livewire(ViewVehicle::class, ['record' => $vehicle->id])
            ->assertActionVisible(EditAction::class)
            ->assertActionHasUrl(EditAction::class, EditVehicle::getUrl(['record' => $vehicle->id]));
    });
});

describe('cost visibility on the view page', function () {
    it('hides all amounts, including the annual total, from a user without the permission', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create(['can_view_costs' => false]));

        $vehicle = Vehicle::factory()->create([
            'monthly_lease_rate' => 450.00,
            'annual_vehicle_tax' => 180.00,
            'annual_insurance' => 620.00,
        ]);

        livewire(ViewVehicle::class, ['record' => $vehicle->id])
            ->assertOk()
            ->assertSchemaComponentHidden('monthly_lease_rate')
            ->assertSchemaComponentHidden('annual_vehicle_tax')
            ->assertSchemaComponentHidden('annual_insurance')
            ->assertSchemaComponentHidden('annual_total_cost')
            ->assertDontSee('450,00 €')
            ->assertDontSee('620,00 €');
    });

    it('shows all amounts, including the annual total, to a user with the permission', function () {
        actingAs(User::factory()->admin()->withCostAccess()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create([
            'monthly_lease_rate' => 450.00,
            'annual_vehicle_tax' => 180.00,
            'annual_insurance' => 620.00,
        ]);

        livewire(ViewVehicle::class, ['record' => $vehicle->id])
            ->assertOk()
            ->assertSchemaComponentVisible('monthly_lease_rate')
            ->assertSchemaComponentVisible('annual_vehicle_tax')
            ->assertSchemaComponentVisible('annual_insurance')
            ->assertSchemaComponentVisible('annual_total_cost');
    });

    it('never exposes cost data via a direct page visit, even without an infolist for it', function () {
        // Anders als bei der Edit-Seite ueberspringt ViewRecord::mount() das
        // Befuellen von $this->data komplett, sobald eine Infolist definiert
        // ist (siehe Filament\Resources\Pages\ViewRecord::mount()) - es gibt
        // also gar keinen Formularzustand, der die Kostenfelder ans Frontend
        // uebertragen koennte.
        actingAs(User::factory()->admin()->withTwoFactor()->create(['can_view_costs' => false]));

        $vehicle = Vehicle::factory()->create(['monthly_lease_rate' => 450.00]);

        $html = livewire(ViewVehicle::class, ['record' => $vehicle->id])->html();

        expect($html)->not->toContain('450,00 €')
            ->not->toContain('450.00');
    });
});

describe('appointment history on the view page', function () {
    it('shows the appointments relation manager', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();

        livewire(ViewVehicle::class, ['record' => $vehicle->id])
            ->assertOk()
            ->assertSeeLivewire(VehicleAppointmentsRelationManager::class);
    });

    it('lets a read-only user see appointments but not create, edit, or delete them', function () {
        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();
        $appointment = VehicleAppointment::factory()->for($vehicle)->create();

        $component = livewire(VehicleAppointmentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => ViewVehicle::class,
        ]);

        $component
            ->assertOk()
            ->assertCanSeeTableRecords([$appointment])
            ->assertTableActionHidden(CreateAction::class)
            ->assertTableActionHidden(EditAction::class, $appointment)
            ->assertTableActionHidden(DeleteAction::class, $appointment);

        // Nicht nur eine versteckte Schaltflaeche: der direkte Aufruf der
        // Aktion wird ebenfalls verweigert.
        $component->mountTableAction(CreateAction::class);
        expect($component->instance()->mountedActions)->toBeEmpty();

        expect(VehicleAppointment::where('vehicle_id', $vehicle->id)->count())->toBe(1);
    });

    it('denies write access to appointments on the view page even for an admin', function () {
        // RelationManager::isReadOnly() prueft auf der Anzeigeseite die
        // Seitenklasse (is_subclass_of($this->getPageClass(), ViewRecord::
        // class)), nicht die Rolle - das gilt also fuer jeden Nutzer, auch
        // Administratoren. Schreiben bleibt der Edit-Seite vorbehalten.
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();
        $appointment = VehicleAppointment::factory()->for($vehicle)->create();

        livewire(VehicleAppointmentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => ViewVehicle::class,
        ])
            ->assertTableActionHidden(CreateAction::class)
            ->assertTableActionHidden(EditAction::class, $appointment)
            ->assertTableActionHidden(DeleteAction::class, $appointment);
    });

    it('still binds the appointment cost column to the viewCosts permission on the view page', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create(['can_view_costs' => false]));

        $vehicle = Vehicle::factory()->create();
        VehicleAppointment::factory()->for($vehicle)->create();

        livewire(VehicleAppointmentsRelationManager::class, [
            'ownerRecord' => $vehicle,
            'pageClass' => ViewVehicle::class,
        ])->assertTableColumnHidden('cost');
    });
});

describe('documents and appointments stacked instead of tabbed', function () {
    // Auftraggeber-Wunsch: Dokumente und Termine sollen untereinander
    // erscheinen statt in Tabs (siehe VehicleResource::getRelations(), eine
    // RelationGroup statt zwei einzelner Eintraege). Ohne diese Gruppierung
    // wuerde Filament automatisch Tabs rendern, sobald mehr als ein Relation
    // Manager registriert ist (count($managers) > 1), und nur der aktive Tab
    // wuerde sein Livewire-Kind mounten.
    it('shows both relation managers at once on the view page', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();

        livewire(ViewVehicle::class, ['record' => $vehicle->id])
            ->assertOk()
            ->assertSeeLivewire(VehicleDocumentsRelationManager::class)
            ->assertSeeLivewire(VehicleAppointmentsRelationManager::class);
    });

    it('shows both relation managers at once on the edit page', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();

        livewire(EditVehicle::class, ['record' => $vehicle->id])
            ->assertOk()
            ->assertSeeLivewire(VehicleDocumentsRelationManager::class)
            ->assertSeeLivewire(VehicleAppointmentsRelationManager::class);
    });

    it('renders both lists with their content immediately, not lazily, on the view page', function () {
        // Belegt nicht nur, dass beide Livewire-Kinder gemountet werden
        // (assertSeeLivewire oben), sondern dass ihr tatsaechlicher Inhalt
        // (Tabellenueberschrift, Datensatz) direkt in der Erstauslieferung
        // steht - siehe VehicleDocumentsRelationManager::$isLazy /
        // VehicleAppointmentsRelationManager::$isLazy.
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();
        VehicleDocument::factory()->for($vehicle)->create(['title' => 'Fahrzeugschein Original']);
        VehicleAppointment::factory()->for($vehicle)->create();

        $html = livewire(ViewVehicle::class, ['record' => $vehicle->id])->html();

        expect($html)->toContain('Dokumente')
            ->toContain('Historie')
            ->toContain('Fahrzeugschein Original');
    });
});

it('places notes after documents and appointments on the view page', function () {
    // Auftraggeber-Wunsch: Fahrzeug, Zuordnung, Vertrag & Service, Kosten,
    // Dokumente, Termine, Notizen - Notizen ganz nach unten. Filament rendert
    // eingebettete Relationsmanager grundsaetzlich erst nach der kompletten
    // Infolist-Schema (siehe EditRecord/ViewRecord::content()); "Notizen"
    // ist deshalb eine eigene, zweite Schema, die ViewVehicle erst nach den
    // Relationsmanagern einbettet (siehe ViewVehicle::content()).
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $vehicle = Vehicle::factory()->create();

    $html = livewire(ViewVehicle::class, ['record' => $vehicle->id])->html();

    $documentsPosition = strpos($html, 'Dokumente');
    $appointmentsPosition = strpos($html, 'Historie');
    $notesPosition = strpos($html, 'Notizen');

    expect($documentsPosition)->not->toBeFalse()
        ->and($appointmentsPosition)->not->toBeFalse()
        ->and($notesPosition)->not->toBeFalse()
        ->and($documentsPosition)->toBeLessThan($appointmentsPosition)
        ->and($appointmentsPosition)->toBeLessThan($notesPosition);
});
