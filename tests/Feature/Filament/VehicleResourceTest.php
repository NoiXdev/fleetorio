<?php

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use App\Filament\Exports\VehicleExporter;
use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\Pages\ListVehicles;
use App\Models\Dealership;
use App\Models\Employee;
use App\Models\FuelCard;
use App\Models\FuelCardProvider;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

describe('the image column', function () {
    it('renders the list without crashing when a vehicle has no image', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create(['image_path' => null]);

        livewire(ListVehicles::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$vehicle]);
    });

    it('renders the list without crashing when a vehicle has an image', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->withImage()->create();

        livewire(ListVehicles::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$vehicle]);
    });

    it('points the image column at the authorized image controller, not the storage disk directly', function () {
        // Belegt den in der Spezifikation geforderten Mechanismus: ImageColumn
        // erhaelt ueber ->state() die URL des autorisierten Controllers statt
        // des rohen Pfads, siehe VehiclesTable.
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->withImage()->create();

        livewire(ListVehicles::class)
            ->assertOk()
            ->assertSee(route('vehicle-images.show', $vehicle), escape: false);
    });
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

it('assigns a dealership to a vehicle via the service section', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $dealership = Dealership::factory()->create();

    livewire(CreateVehicle::class)
        ->fillForm([
            'brand' => 'VW',
            'model' => 'Passat',
            'license_plate' => 'M-SV 0001',
            'fuel_type' => FuelType::Diesel->value,
            'lease_start' => '2026-01-01',
            'lease_end' => '2029-01-01',
            'dealership_id' => $dealership->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Vehicle::class, [
        'license_plate' => 'M-SV 0001',
        'dealership_id' => $dealership->id,
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

describe('the vin field', function () {
    it('accepts a valid vin', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        livewire(CreateVehicle::class)
            ->fillForm([
                'brand' => 'VW',
                'model' => 'Passat',
                'license_plate' => 'M-VN 0001',
                'vin' => 'WVWZZZ1KZAW123456',
                'fuel_type' => FuelType::Diesel->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        assertDatabaseHas(Vehicle::class, [
            'license_plate' => 'M-VN 0001',
            'vin' => 'WVWZZZ1KZAW123456',
        ]);
    });

    it('allows an empty vin, since older vehicles do not always have one on record', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        livewire(CreateVehicle::class)
            ->fillForm([
                'brand' => 'VW',
                'model' => 'Passat',
                'license_plate' => 'M-VN 0002',
                'vin' => null,
                'fuel_type' => FuelType::Diesel->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    });

    it('rejects a vin that is too short', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        livewire(CreateVehicle::class)
            ->fillForm([
                'brand' => 'VW',
                'model' => 'Passat',
                'license_plate' => 'M-VN 0003',
                'vin' => 'WVWZZZ1KZAW12345',
                'fuel_type' => FuelType::Diesel->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['vin' => 'regex']);
    });

    it('rejects a vin containing the letter i, which vins may never contain', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        livewire(CreateVehicle::class)
            ->fillForm([
                'brand' => 'VW',
                'model' => 'Passat',
                'license_plate' => 'M-VN 0004',
                'vin' => 'WVWZZZ1KZAWI23456',
                'fuel_type' => FuelType::Diesel->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['vin' => 'regex']);
    });

    it('uppercases a lowercase vin before validating and storing it', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        livewire(CreateVehicle::class)
            ->fillForm([
                'brand' => 'VW',
                'model' => 'Passat',
                'license_plate' => 'M-VN 0005',
                'vin' => 'wvwzzz1kzaw123456',
                'fuel_type' => FuelType::Diesel->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        assertDatabaseHas(Vehicle::class, [
            'license_plate' => 'M-VN 0005',
            'vin' => 'WVWZZZ1KZAW123456',
        ]);
    });
});

it('does not show the vin in the vehicle list', function () {
    // Die Liste ist breit genug und die VIN ist nichts, wonach man visuell
    // sucht (Auftraggeber-Entscheidung) - anders als beim Export bleibt sie
    // hier aussen vor.
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    Vehicle::factory()->create(['vin' => 'WVWZZZ1KZAW123456']);

    livewire(ListVehicles::class)
        ->assertOk()
        ->assertDontSee('WVWZZZ1KZAW123456');
});

it('includes the vin as a column in the vehicle export', function () {
    $vinColumn = collect(VehicleExporter::getColumns())
        ->first(fn ($column) => $column->getName() === 'vin');

    expect($vinColumn)->not->toBeNull();
});

it('labels the fuel card option with provider and full card number', function () {
    // Auftraggeber-Entscheidung: Die Zuordnung war mit der maskierten Nummer
    // umstaendlich. Anbieter + volle Nummer, damit man bei mehreren Anbietern
    // nicht raten muss.
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    // "DKV" ist einer der Startwerte aus der Migration
    // 2026_07_30_180000_create_fuel_card_providers_table, kein eigens
    // angelegter Anbieter - der Name ist damit in jedem Testlauf eindeutig.
    FuelCard::factory()->create([
        'fuel_card_provider_id' => FuelCardProvider::where('name', 'DKV')->value('id'),
        'card_number' => '7080123456789',
    ]);

    livewire(CreateVehicle::class)
        ->assertSee('DKV 7080123456789');
});

it('shows a short placeholder instead of Filaments default long one on the selects without a default value', function () {
    // Filaments deutsche Übersetzung setzt fuer Select-Felder ohne eigenen
    // Platzhalter den Text "Wählen Sie eine Option" (siehe vendor/filament/
    // forms/resources/lang/de/components.php, Schluessel select.placeholder).
    // Das betrifft hier nicht nur den Leasinggeber, sondern auch Mitarbeiter,
    // Tankkarte, Antriebsart und (seit der Autohaus-Erweiterung) das Autohaus
    // - alle fuenf haben keinen Standardwert.
    //
    // Status hat einen Standardwert (VehicleStatus::Active) und zeigt daher
    // nie sichtbar einen Platzhalter; er bleibt bewusst unangetastet - sein
    // interner JS-Konfigurationswert traegt also weiterhin den langen Text
    // (nie gerendert), weshalb dieser Test die restliche Zahl der Treffer
    // prueft statt pauschal auf Abwesenheit.
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $html = livewire(CreateVehicle::class)->html();

    expect(substr_count($html, 'Wählen Sie eine Option'))->toBe(1)
        ->and(substr_count($html, 'Auswählen'))->toBe(5);
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

describe('the image field', function () {
    it('previews an already stored image via the authorized controller url, not a broken storage link', function () {
        // Ohne die eigene getUploadedFileUsing()-Ueberschreibung wuerde
        // Filaments Standardverhalten fuer den privaten 'local'-Disk zuerst
        // Storage::temporaryUrl() versuchen (der 'local'-Treiber unterstuetzt
        // das nicht) und danach auf Storage::url() zurueckfallen, was ohne
        // symbolischen Link auf ein nicht erreichbares /storage/... zeigt -
        // siehe Kommentar in VehicleForm.
        //
        // Die eigentliche Vorschau-URL wird erst per asynchronem Livewire-
        // Aufruf aus dem Javascript des FileUpload-Feldes nachgeladen (siehe
        // vendor/filament/forms/src/Components/FileUpload.php,
        // "getUploadedFilesUsing"), steht also nicht in der initialen HTML-
        // Auslieferung - deshalb wird hier direkt die PHP-Methode dahinter
        // (getUploadedFiles()) am gemounteten Formularfeld geprueft, statt im
        // gerenderten HTML zu suchen.
        Storage::fake('local');

        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->withImage()->create();
        Storage::disk('local')->put($vehicle->image_path, 'dummy image content');

        $component = livewire(EditVehicle::class, ['record' => $vehicle->id]);

        $field = $component->instance()->getSchema('form')->getComponent('image_path');

        $uploadedFiles = $field->getUploadedFiles();

        expect($uploadedFiles)->toHaveCount(1);

        $preview = array_values($uploadedFiles)[0];

        expect($preview['url'])->toBe(route('vehicle-images.show', $vehicle));
    });

    it('still stores an uploaded image on the private disk, not the public one', function () {
        // Belegt, dass ein Upload trotz imageEditor()/imageAspectRatio()/
        // automaticallyResizeImagesToWidth() weiterhin funktioniert. Die
        // Verkleinerung auf 2000 Pixel selbst laeuft ausschliesslich im
        // Browser (FilePondPluginImageResize, siehe Bericht) - ein
        // fillForm()-Test im PHP-Prozess durchlaeuft diesen JS-Schritt nicht
        // und kann seine Wirkung deshalb hier nicht belegen. 800x600 (4:3)
        // besteht aber die serverseitige Rule::dimensions()->ratio()-Pruefung
        // aus imageAspectRatio('4:3'), die unabhaengig vom Editor greift.
        Storage::fake('local');
        Storage::fake('public');

        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $file = UploadedFile::fake()->image('fahrzeug.jpg', 800, 600);

        livewire(CreateVehicle::class)
            ->fillForm([
                'brand' => 'VW',
                'model' => 'Passat',
                'license_plate' => 'M-IMG 0001',
                'fuel_type' => FuelType::Diesel->value,
                'image_path' => $file,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $vehicle = Vehicle::where('license_plate', 'M-IMG 0001')->firstOrFail();

        Storage::disk('local')->assertExists($vehicle->image_path);
        Storage::disk('public')->assertMissing($vehicle->image_path);
    });

    it('rejects an image that is not cropped to 4:3, even bypassing the browser editor', function () {
        // Serverseitige Zweitpruefung fuer den Fall, dass ein Bild ohne
        // Browser-JS eingereicht wird (siehe Kommentar in VehicleForm):
        // imageAspectRatio('4:3') erzeugt Rule::dimensions()->ratio(4/3),
        // unabhaengig vom clientseitigen Bildeditor.
        Storage::fake('local');

        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $file = UploadedFile::fake()->image('quadratisch.jpg', 800, 800);

        $component = livewire(CreateVehicle::class)
            ->fillForm([
                'brand' => 'VW',
                'model' => 'Passat',
                'license_plate' => 'M-IMG 0002',
                'fuel_type' => FuelType::Diesel->value,
                'image_path' => $file,
            ])
            ->call('create')
            ->assertHasFormErrors(['image_path']);

        // Rule::dimensions() liefert die generische Laravel-Meldung
        // "invalid image dimensions" statt eines einfachen "dimensions"-
        // Regelnamens (siehe BaseFileUpload::getRules()) - assertHasFormErrors()
        // oben belegt nur, dass ueberhaupt ein Fehler an diesem Feld haengt.
        expect($component->instance()->getErrorBag()->first('data.image_path'))
            ->toContain('invalid image dimensions');
    });
});

it('renders the edit page without crashing when every optional field is empty', function () {
    // Spiegelbild des gleichnamigen Tests fuer die Anzeigeseite
    // (VehicleViewPageTest): kein Bild, keine Zuordnungen, keine
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

    livewire(EditVehicle::class, ['record' => $vehicle->id])
        ->assertOk();
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
    //
    // Ausgemustert, weil Loeschen seit der Umstellung genau das voraussetzt -
    // ein Fahrzeug im Bestand bietet den Knopf gar nicht erst an (siehe
    // EditVehicle::deleteAction und DeactivateInsteadOfDeleteTest).
    $vehicle = Vehicle::factory()->retired()->create();

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

it('places notes after documents and appointments on the edit page', function () {
    // Auftraggeber-Wunsch: Fahrzeug, Zuordnung, Vertrag & Service, Kosten,
    // Dokumente, Termine, Notizen - Notizen ganz nach unten, auch im
    // Formular. Siehe EditVehicle::content() fuer den Mechanismus (eigene
    // 'notesForm'-Schema, erst nach den Relationsmanagern eingebettet).
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $vehicle = Vehicle::factory()->create();

    $html = livewire(EditVehicle::class, ['record' => $vehicle->id])->html();

    $documentsPosition = strpos($html, 'Dokumente');
    $appointmentsPosition = strpos($html, 'Historie');
    $notesPosition = strpos($html, 'Notizen');

    expect($documentsPosition)->not->toBeFalse()
        ->and($appointmentsPosition)->not->toBeFalse()
        ->and($notesPosition)->not->toBeFalse()
        ->and($documentsPosition)->toBeLessThan($appointmentsPosition)
        ->and($appointmentsPosition)->toBeLessThan($notesPosition);
});

it('still saves notes correctly even though they render in a separate schema after the relation managers', function () {
    // Die Notizen-Schema teilt sich den statePath 'data' mit der 'form'-
    // Schema (siehe EditVehicle::defaultNotesForm()), landet aber nicht mehr
    // in deren getState() - EditVehicle::mutateFormDataBeforeSave() setzt
    // den Wert von Hand wieder ein. Dieser Test belegt, dass das
    // tatsaechlich funktioniert, nicht nur dass die Reihenfolge stimmt.
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $vehicle = Vehicle::factory()->create(['notes' => 'Alte Notiz']);

    livewire(EditVehicle::class, ['record' => $vehicle->id])
        ->fillForm(['notes' => 'Neue Notiz'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($vehicle->refresh()->notes)->toBe('Neue Notiz');
});

describe('the button row on the edit page', function () {
    it('offers "Speichern" in the header, saving without leaving the page', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create(['brand' => 'VW']);

        livewire(EditVehicle::class, ['record' => $vehicle->id])
            ->assertActionExists('save')
            ->fillForm(['brand' => 'Audi'])
            ->callAction('save')
            ->assertHasNoFormErrors();

        expect($vehicle->refresh()->brand)->toBe('Audi');
    });

    it('offers "Speichern und schließen" at the bottom, saving and redirecting away', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create(['brand' => 'VW']);

        livewire(EditVehicle::class, ['record' => $vehicle->id])
            ->fillForm(['brand' => 'Audi'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        expect($vehicle->refresh()->brand)->toBe('Audi');
    });

    it('keeps a plain "Abbrechen" action available', function () {
        // Abbrechen ist ein reiner Alpine-Klick-Handler ohne Bestaetigung
        // (kein requiresConfirmation()) und braucht deshalb - anders als
        // "Löschen" - keine eigene {name}Action()-Methode, um klickbar zu
        // sein (siehe Kommentar an EditVehicle::deleteAction()). Die
        // Beschriftung ist statischer Text und erscheint deshalb zuverlaessig
        // im gerenderten HTML, anders als ein gebundener Feldwert.
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();

        livewire(EditVehicle::class, ['record' => $vehicle->id])
            ->assertSee('Abbrechen');
    });

    it('keeps the delete action reachable, requiring confirmation, styled as an outline instead of a solid button', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $vehicle = Vehicle::factory()->create();

        $page = livewire(EditVehicle::class, ['record' => $vehicle->id])
            ->assertActionExists(DeleteAction::class)
            ->instance();

        $action = $page->deleteAction();

        expect($action->isOutlined())->toBeTrue()
            ->and($action->getColor())->toBe('danger')
            ->and($action->isConfirmationRequired())->toBeTrue();
    });
});

describe('searching by driver name', function () {
    it('finds a vehicle in the list by searching the driver last name', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $employee = Employee::factory()->create(['first_name' => 'Annastasia', 'last_name' => 'Beispielmann']);
        $vehicle = Vehicle::factory()->for($employee)->create();
        $other = Vehicle::factory()->create();

        livewire(ListVehicles::class)
            ->searchTable('Beispielmann')
            ->assertCanSeeTableRecords([$vehicle])
            ->assertCanNotSeeTableRecords([$other]);
    });

    it('finds a vehicle in the list by searching the driver first name', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $employee = Employee::factory()->create(['first_name' => 'Annastasia', 'last_name' => 'Beispielmann']);
        $vehicle = Vehicle::factory()->for($employee)->create();
        $other = Vehicle::factory()->create();

        livewire(ListVehicles::class)
            ->searchTable('Annastasia')
            ->assertCanSeeTableRecords([$vehicle])
            ->assertCanNotSeeTableRecords([$other]);
    });
});
