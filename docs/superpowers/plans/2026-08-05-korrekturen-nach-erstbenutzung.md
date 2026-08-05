# Korrekturen nach der ersten Benutzung — Umsetzungsplan

> **Für agentische Bearbeiter:** Dieser Plan wird Aufgabe für Aufgabe abgearbeitet. Die Schritte
> nutzen Checkbox-Syntax (`- [ ]`). Grundlage ist
> `docs/superpowers/specs/2026-08-05-korrekturen-nach-erstbenutzung-design.md`.

**Ziel:** Fünf Rückmeldungen aus der ersten echten Benutzung umsetzen — Deaktivieren statt
Löschen, 2FA-Steuerung für Admins, Bruttolistenpreis, „Historie" statt „Termine", und der
Bildupload-Fehler bei kleinen Bildern.

**Architektur:** Kein neues Konzept. Der vorhandene Zustand (`vehicles.status`,
`employees.active`) bekommt eine Wirkung und wird auf die übrigen Stammdaten ausgeweitet. Löschen
wird an diesen Zustand gebunden. Alles andere sind punktuelle Ergänzungen an bestehenden Klassen.

**Tech-Stack:** Laravel 13, Filament 5, Livewire 4, MariaDB 11.8, Pest 4, PHP 8.4, DDEV.

## Globale Vorgaben

Diese gelten für **jede** Aufgabe:

- Alle Befehle laufen über DDEV: `ddev artisan …`, `ddev composer …`, `ddev exec vendor/bin/pest`.
- **Niemals** `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, `db:seed` — die
  Entwicklungsdatenbank enthält echte Arbeitsdaten. Nur `ddev artisan migrate` (vorwärts).
- **Keine schreibenden `tinker`-Befehle** gegen die Entwicklungsdatenbank. Lesende sind in Ordnung.
- Code, Klassennamen, Spaltennamen und Commit-Messages englisch. Oberflächentexte deutsch, Labels
  immer explizit (`->label('Kennzeichen')`).
- Die Testsuite läuft gegen MariaDB (Datenbank `test`, siehe `phpunit.xml`), nicht gegen SQLite —
  nur so werden die Fremdschlüssel mit `restrictOnDelete` echt geprüft.
- Vor jedem Commit: `ddev exec vendor/bin/pint` und `ddev exec vendor/bin/pest`.
- TDD: erst der Test, dann ihn scheitern sehen, dann die Umsetzung, dann bestehen sehen, dann
  committen. Ein RED-Beweis ist die **kopierte Ausgabe** des Testlaufs, keine Behauptung.

## Dateiübersicht

**Aufgabe 1** (eigener Branch `fix/vehicle-image-upload`):
- Ändern: `app/Filament/Resources/Vehicles/Schemas/VehicleForm.php` (Bildfeld)
- Test: `tests/Feature/Filament/VehicleImageUploadTest.php` (neu)

**Aufgaben 2–7** (Branch `feature/archiving-and-admin-controls`):
- Neu: `database/migrations/2026_08_05_100000_add_active_to_master_data_tables.php`
- Neu: `database/migrations/2026_08_05_100001_add_gross_list_price_to_vehicles_table.php`
- Neu: `database/migrations/2026_08_05_100002_add_requires_two_factor_to_users_table.php`
- Neu: `app/Filament/Actions/DeactivateAction.php`, `app/Filament/Actions/ActivateAction.php`
- Neu: `app/Http/Middleware/EnsureTwoFactorWhenRequired.php`
- Ändern: die Modelle `Dealership`, `LeasingCompany`, `FuelCard`, `FuelCardProvider` (`$fillable`,
  `casts`), `Vehicle` (`$fillable`, Relationen), `User` (`$fillable`, `casts`)
- Ändern: je Resource die Klassen unter `Schemas/` (Formular, Infolist) und `Tables/`
- Ändern: `app/Enums/AppointmentType.php`, `app/Providers/Filament/AdminPanelProvider.php`
- Ändern: `app/Filament/Actions/GuardedDeleteAction.php` (nur noch sichtbar bei inaktiv)

---

### Aufgabe 1: Bildupload nimmt beliebige Bildgrößen an

**Branch:** `fix/vehicle-image-upload`, abzweigen von `main`.

**Dateien:**
- Ändern: `app/Filament/Resources/Vehicles/Schemas/VehicleForm.php:205-259`
- Test: `tests/Feature/Filament/VehicleImageUploadTest.php` (neu)

**Schnittstellen:**
- Verbraucht: nichts aus anderen Aufgaben.
- Erzeugt: nichts, was andere Aufgaben brauchen.

- [ ] **Schritt 1: Branch anlegen**

```bash
git switch main && git pull --ff-only origin main
git switch -c fix/vehicle-image-upload
```

- [ ] **Schritt 2: Den scheiternden Test schreiben**

Datei `tests/Feature/Filament/VehicleImageUploadTest.php`:

```php
<?php

use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
    Storage::fake('local');
});

// Der Kern des Fehlers: imageAspectRatio('4:3') erzeugt serverseitig
// Rule::dimensions()->ratio(). Ein Bild in einem anderen Verhaeltnis wurde
// dadurch abgelehnt, obwohl die Groesse laut Anforderung egal sein soll.
it('accepts an image that is not in a 4:3 ratio', function () {
    $vehicle = Vehicle::factory()->create(['image_path' => null]);

    livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->fillForm(['image_path' => [UploadedFile::fake()->image('panorama.jpg', 1600, 400)]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($vehicle->fresh()->image_path)->not->toBeNull();
});

it('accepts an image far below the resize width of 2000 pixels', function () {
    $vehicle = Vehicle::factory()->create(['image_path' => null]);

    livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->fillForm(['image_path' => [UploadedFile::fake()->image('klein.jpg', 800, 600)]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($vehicle->fresh()->image_path)->not->toBeNull();
});

it('still rejects an image above the ten megabyte limit', function () {
    $vehicle = Vehicle::factory()->create(['image_path' => null]);

    livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->fillForm([
            'image_path' => [UploadedFile::fake()->image('gross.jpg', 4000, 3000)->size(11 * 1024)],
        ])
        ->call('save')
        ->assertHasFormErrors(['image_path']);
});
```

- [ ] **Schritt 3: Test laufen lassen und scheitern sehen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/VehicleImageUploadTest.php
```

Erwartet: Der erste Test scheitert mit einem Formularfehler auf `image_path` (die
`dimensions`-Regel). Die Ausgabe in den Commit-Verlauf übernehmen, nicht paraphrasieren.

- [ ] **Schritt 4: Das Bildfeld ändern**

In `VehicleForm.php` im Feld `image_path`:

1. Die Zeilen `->imageAspectRatio('4:3')` und `->automaticallyOpenImageEditorForAspectRatio()`
   **entfernen**.
2. `->automaticallyResizeImagesMode('cover')` zu `->automaticallyResizeImagesMode('contain')`
   ändern. Begründung: Ohne festes Seitenverhältnis würde `cover` ein Panorama auf ein Quadrat
   beschneiden, weil keine Zielhöhe gesetzt ist. `contain` skaliert nur herunter und schneidet nie.
3. `->automaticallyUpscaleImagesWhenResizing(false)` **ergänzen**.
4. Aus `->validationMessages([...])` den Eintrag `'dimensions' => …` **entfernen** — die Regel
   gibt es nicht mehr, die Meldung wäre tote Konfiguration.
5. Den Kommentarblock über den entfernten Zeilen anpassen: Er beschreibt heute den erzwungenen
   4:3-Zuschnitt. Neu soll dort stehen, dass der Editor manuell verfügbar bleibt, dass die
   Bildgröße bewusst nicht mehr eingeschränkt wird, und dass `automaticallyUpscaleImagesWhenResizing(false)`
   verhindert, dass kleine Bilder auf 2000 Pixel hochgerechnet und dadurch unscharf werden.

Ergebnis (der Teil der Kette, der sich ändert):

```php
->imageEditor()
->automaticallyResizeImagesMode('contain')
->automaticallyResizeImagesToWidth('2000')
->automaticallyUpscaleImagesWhenResizing(false)
```

- [ ] **Schritt 5: Test laufen lassen und bestehen sehen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/VehicleImageUploadTest.php
```

Erwartet: alle drei Tests grün.

- [ ] **Schritt 6: Darstellung auf beliebige Seitenverhältnisse einstellen**

Bilder haben ab jetzt beliebige Formen. Überall dort, wo ein Fahrzeugbild angezeigt wird
(`VehiclesTable` als `ImageColumn`, die Detailseite als `ImageEntry`, die Vorschau im Formular),
prüfen, dass ein fester Rahmen mit `object-fit: cover` gesetzt ist — sonst werden Zeilen und
Kacheln unterschiedlich hoch. Filaments `ImageColumn` bietet dafür `->imageSize()` und
`->extraImgAttributes(['class' => 'object-cover'])`.

Die Stellen finden mit:

```bash
grep -rn "image_path" app/Filament/ resources/views/
```

- [ ] **Schritt 7: Volle Suite, Pint, Commit**

```bash
ddev exec vendor/bin/pint
ddev exec vendor/bin/pest
git add -A
git commit -m "fix: accept vehicle images of any size and aspect ratio"
```

- [ ] **Schritt 8: Im Browser gegenprüfen**

Ein Bild mit 800×600 hochladen und danach die gespeicherte Datei ansehen: Sie muss **800×600**
sein, nicht auf 2000 Pixel hochgerechnet. Ein Bild mit 4000 Pixel Breite hochladen: Es muss auf
2000 Pixel Breite herunterskaliert ankommen, mit unverändertem Seitenverhältnis. Diese beiden
Werte tatsächlich ablesen — das Verhalten des FilePond-Plugins ist die eine Sache an dieser
Aufgabe, die sich nicht durch einen serverseitigen Test belegen lässt, weil die Verkleinerung im
Browser passiert.

- [ ] **Schritt 9: Branch pushen und PR öffnen**

```bash
git push -u origin fix/vehicle-image-upload
gh pr create --base main --title "fix: accept vehicle images of any size and aspect ratio" \
  --body "Beschreibung: Ursache (Upscaling auf 2000 Pixel und die serverseitige
4:3-Regel), Änderung, und die im Browser abgelesenen Bildmaße aus Schritt 8."
```

---

### Aufgabe 2: Feld `active` für die übrigen Stammdaten

**Branch:** `feature/archiving-and-admin-controls` (existiert bereits, enthält die Spec).

**Dateien:**
- Neu: `database/migrations/2026_08_05_100000_add_active_to_master_data_tables.php`
- Ändern: `app/Models/Dealership.php`, `app/Models/LeasingCompany.php`,
  `app/Models/FuelCard.php`, `app/Models/FuelCardProvider.php`
- Ändern: die `Schemas/*Form.php`, `Schemas/*Infolist.php` und `Tables/*Table.php` dieser vier
  Resources
- Test: `tests/Feature/MasterDataActiveFlagTest.php` (neu)

**Schnittstellen:**
- Verbraucht: nichts.
- Erzeugt: Spalte `active` (boolean, Standard `true`) auf `dealerships`, `leasing_companies`,
  `fuel_cards`, `fuel_card_providers`; jeweils `'active' => 'boolean'` in `casts()` und `'active'`
  in `$fillable`. Aufgaben 3 und 4 setzen das voraus.

- [ ] **Schritt 1: Den scheiternden Test schreiben**

Datei `tests/Feature/MasterDataActiveFlagTest.php`:

```php
<?php

use App\Models\Dealership;
use App\Models\FuelCard;
use App\Models\FuelCardProvider;
use App\Models\LeasingCompany;

// Der Standard muss "aktiv" sein: Bestandsdaten, die es vor dieser Migration
// schon gab, sollen unveraendert benutzbar bleiben.
it('creates master data as active by default', function (string $modelClass) {
    $record = $modelClass::factory()->create();

    expect($record->active)->toBeTrue();
})->with([
    Dealership::class,
    LeasingCompany::class,
    FuelCard::class,
    FuelCardProvider::class,
]);

it('casts the active flag to a boolean', function (string $modelClass) {
    $record = $modelClass::factory()->create(['active' => false]);

    expect($record->fresh()->active)->toBeFalse();
})->with([
    Dealership::class,
    LeasingCompany::class,
    FuelCard::class,
    FuelCardProvider::class,
]);
```

- [ ] **Schritt 2: Test laufen lassen und scheitern sehen**

```bash
ddev exec vendor/bin/pest tests/Feature/MasterDataActiveFlagTest.php
```

Erwartet: `Column not found: 1054 Unknown column 'active'`.

- [ ] **Schritt 3: Migration schreiben**

```bash
ddev artisan make:migration add_active_to_master_data_tables
```

Die erzeugte Datei so füllen (Dateiname der Übersicht oben angleichen, falls der Zeitstempel
abweicht):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Der Zustand "aktiv/inaktiv" existierte bisher nur am Mitarbeiter
 * (employees.active) und - unter anderem Namen - am Fahrzeug
 * (vehicles.status). Damit "Loeschen nur, wenn inaktiv" fuer alle Stammdaten
 * gilt, brauchen die uebrigen vier dasselbe Feld.
 *
 * Standard true, damit vorhandene Datensaetze unveraendert nutzbar bleiben.
 */
return new class extends Migration
{
    private const TABLES = [
        'dealerships',
        'leasing_companies',
        'fuel_cards',
        'fuel_card_providers',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->boolean('active')->default(true);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('active');
            });
        }
    }
};
```

- [ ] **Schritt 4: Migration ausführen**

```bash
ddev artisan migrate
```

- [ ] **Schritt 5: Die vier Modelle ergänzen**

In `Dealership`, `LeasingCompany`, `FuelCard` und `FuelCardProvider` jeweils:

- `'active'` in `$fillable` aufnehmen,
- `'active' => 'boolean'` in `casts()` aufnehmen,
- `protected $attributes = ['active' => true];` ergänzen, damit ein frisch erzeugtes Modell
  denselben Zustand hat wie eine frisch geschriebene Zeile — dasselbe Muster wie in `User`.

- [ ] **Schritt 6: Test laufen lassen und bestehen sehen**

```bash
ddev exec vendor/bin/pest tests/Feature/MasterDataActiveFlagTest.php
```

- [ ] **Schritt 7: Feld in Formular, Liste und Detailseite aufnehmen**

Für jede der vier Resources, wortgleich zum bestehenden Muster beim Mitarbeiter
(`EmployeeForm.php:30`, `EmployeesTable.php:32`, `EmployeeInfolist.php:41`):

- Formular: `Toggle::make('active')->label('Aktiv')->default(true)`
- Liste: `IconColumn::make('active')->label('Aktiv')->boolean()`
- Detailseite: `IconEntry::make('active')->label('Aktiv')->boolean()`

- [ ] **Schritt 8: Volle Suite, Pint, Commit**

```bash
ddev exec vendor/bin/pint
ddev exec vendor/bin/pest
git add -A
git commit -m "feat: add an active flag to the remaining master data"
```

---

### Aufgabe 3: Deaktivieren, Aktivieren, und Löschen nur bei inaktiv

**Dateien:**
- Neu: `app/Filament/Actions/DeactivateAction.php`, `app/Filament/Actions/ActivateAction.php`
- Ändern: `app/Filament/Actions/GuardedDeleteAction.php`
- Ändern: alle sechs `Tables/*Table.php` (Employees, Dealerships, LeasingCompanies, FuelCards,
  FuelCardProviders, Vehicles)
- Test: `tests/Feature/Filament/DeactivateInsteadOfDeleteTest.php` (neu)

**Schnittstellen:**
- Verbraucht: Spalte `active` aus Aufgabe 2.
- Erzeugt:
  - `DeactivateAction::make()` — setzt das Zustandsfeld auf „inaktiv". Standardmäßig `active`;
    über `->stateColumn(string $column, mixed $inactiveValue, mixed $activeValue)` umstellbar für
    das Fahrzeug (`status`, `VehicleStatus::Retired`, `VehicleStatus::Active`).
  - `ActivateAction::make()` — dieselbe Schnittstelle, setzt auf „aktiv".
  - `GuardedDeleteAction` ist ab jetzt nur bei inaktiven Datensätzen sichtbar.

- [ ] **Schritt 1: Den scheiternden Test schreiben**

Datei `tests/Feature/Filament/DeactivateInsteadOfDeleteTest.php`:

```php
<?php

use App\Enums\VehicleStatus;
use App\Filament\Actions\ActivateAction;
use App\Filament\Actions\DeactivateAction;
use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\Employees\Pages\ListEmployees;
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
        ->assertTableActionVisible(GuardedDeleteAction::class, $employee)
        ->assertTableActionVisible(ActivateAction::class, $employee)
        ->assertTableActionHidden(DeactivateAction::class, $employee);
});

// Der Kern der Anforderung: Ausscheiden aus der Firma darf nicht daran
// scheitern, dass noch ein Auto zugeordnet ist. Die Zuordnung bleibt stehen.
it('deactivates an employee who still has a vehicle, keeping the assignment', function () {
    $employee = Employee::factory()->create(['active' => true]);
    $vehicle = Vehicle::factory()->for($employee)->create();

    livewire(ListEmployees::class)
        ->callTableAction(DeactivateAction::class, $employee);

    assertDatabaseHas(Employee::class, ['id' => $employee->id, 'active' => false]);
    expect($vehicle->fresh()->employee_id)->toBe($employee->id);
});

it('refuses to delete an inactive employee who is still assigned to a vehicle', function () {
    $employee = Employee::factory()->create(['active' => false]);
    Vehicle::factory()->for($employee)->create();

    livewire(ListEmployees::class)
        ->callTableAction(GuardedDeleteAction::class, $employee);

    assertDatabaseHas(Employee::class, ['id' => $employee->id]);
});

it('deletes an inactive employee who is no longer assigned', function () {
    $employee = Employee::factory()->create(['active' => false]);

    livewire(ListEmployees::class)
        ->callTableAction(GuardedDeleteAction::class, $employee);

    expect(Employee::find($employee->id))->toBeNull();
});

it('retires a vehicle instead of deleting it while it is active', function () {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);

    livewire(ListVehicles::class)
        ->assertTableActionHidden(GuardedDeleteAction::class, $vehicle)
        ->callTableAction(DeactivateAction::class, $vehicle);

    expect($vehicle->fresh()->status)->toBe(VehicleStatus::Retired);
});

it('activates an inactive employee again', function () {
    $employee = Employee::factory()->create(['active' => false]);

    livewire(ListEmployees::class)
        ->callTableAction(ActivateAction::class, $employee);

    assertDatabaseHas(Employee::class, ['id' => $employee->id, 'active' => true]);
});
```

- [ ] **Schritt 2: Test laufen lassen und scheitern sehen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/DeactivateInsteadOfDeleteTest.php
```

Erwartet: `Class "App\Filament\Actions\DeactivateAction" not found`.

- [ ] **Schritt 3: Die beiden Aktionen schreiben**

`app/Filament/Actions/DeactivateAction.php`:

```php
<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Ersetzt das Loeschen, solange ein Datensatz aktiv ist. Die Anwendung
 * verwirft Stammdaten nicht mehr sofort: erst wird deaktiviert, und nur ein
 * inaktiver Datensatz laesst sich ueberhaupt loeschen (siehe
 * GuardedDeleteAction).
 *
 * Der Zustand steckt nicht ueberall in derselben Spalte - das Fahrzeug fuehrt
 * ihn als Enum in `status`, die Stammdaten als boolesches `active`. Deshalb
 * sind Spalte und Werte ueber stateColumn() umstellbar.
 */
class DeactivateAction extends Action
{
    protected string $stateColumn = 'active';

    protected mixed $inactiveValue = false;

    protected mixed $activeValue = true;

    public static function getDefaultName(): ?string
    {
        return 'deactivate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Deaktivieren');
        $this->icon('heroicon-m-archive-box');
        $this->color('gray');
        $this->requiresConfirmation();
        $this->modalHeading('Eintrag deaktivieren');
        $this->modalDescription(
            'Der Eintrag bleibt erhalten und behält seine Zuordnungen, taucht aber '
            .'nicht mehr in Auswahllisten auf. Löschen ist danach möglich.'
        );
        $this->modalSubmitActionLabel('Deaktivieren');

        $this->visible(fn (Model $record): bool => $record->{$this->stateColumn} === $this->activeValue);

        $this->action(function (Model $record): void {
            $record->{$this->stateColumn} = $this->inactiveValue;
            $record->save();

            Notification::make()
                ->success()
                ->title('Eintrag deaktiviert')
                ->send();
        });
    }

    public function stateColumn(string $column, mixed $inactiveValue, mixed $activeValue): static
    {
        $this->stateColumn = $column;
        $this->inactiveValue = $inactiveValue;
        $this->activeValue = $activeValue;

        return $this;
    }
}
```

`app/Filament/Actions/ActivateAction.php`: dieselbe Struktur, aber

- `getDefaultName()` gibt `'activate'` zurück,
- Label „Aktivieren", Icon `heroicon-m-arrow-uturn-left`, Farbe `success`,
- `visible()` prüft auf **Ungleichheit** zum aktiven Wert,
- die Aktion setzt `$this->activeValue`,
- Meldung „Eintrag aktiviert".

Keine Rückfrage — Aktivieren ist folgenlos umkehrbar.

- [ ] **Schritt 4: `GuardedDeleteAction` nur noch bei inaktiven zeigen**

In `app/Filament/Actions/GuardedDeleteAction.php` dieselben drei Eigenschaften und dieselbe
`stateColumn()`-Methode ergänzen wie oben, und in `setUp()` ergänzen:

```php
// Loeschen ist erst der zweite Schritt: Ein aktiver Datensatz wird
// deaktiviert (DeactivateAction), und nur ein inaktiver laesst sich
// entfernen. Das ist die eigentliche Regel dieser Aenderung - der
// bestehende before()-Rumpf darunter bleibt als Erklaerung fuer den Fall,
// dass der Fremdschluessel trotzdem im Weg steht.
$this->visible(fn (Model $record): bool => $record->{$this->stateColumn} !== $this->activeValue);
```

Zusätzlich `modalHeading('Endgültig löschen')` setzen und im bestehenden `before()`-Rumpf den
Titel der Meldung von „Löschen nicht möglich" auf „Endgültig löschen nicht möglich" ändern.

- [ ] **Schritt 5: Die sechs Tabellen umstellen**

In jeder `Tables/*Table.php` bei `recordActions()` ergänzen — Reihenfolge: Ansehen, Bearbeiten,
Aktivieren/Deaktivieren, Löschen.

Für die fünf Stammdaten (Standardwerte passen):

```php
DeactivateAction::make(),
ActivateAction::make(),
GuardedDeleteAction::make(),
```

Für `VehiclesTable` mit umgestellter Spalte — **hier nur die beiden Zustandsaktionen**, siehe den
nächsten Schritt:

```php
DeactivateAction::make()
    ->label('Ausmustern')
    ->modalHeading('Fahrzeug ausmustern')
    ->modalDescription(
        'Das Fahrzeug bleibt mit seiner gesamten Historie erhalten, zählt aber '
        .'nicht mehr zum aktiven Bestand. Löschen ist danach möglich.'
    )
    ->stateColumn('status', VehicleStatus::Retired, VehicleStatus::Active),
ActivateAction::make()
    ->label('Wieder aktivieren')
    ->stateColumn('status', VehicleStatus::Retired, VehicleStatus::Active),
```

- [ ] **Schritt 5b: Das Löschen des Fahrzeugs liegt woanders**

`VehiclesTable` hat **keine** Löschaktion. Beim Fahrzeug hängt das Löschen am Formularfuß der
Bearbeitungsseite: `EditVehicle::getFormActions()` gibt unter anderem `$this->deleteAction()`
zurück — Filaments eingebaute `DeleteAction`, absichtlich mit `->outlined()` zurückhaltend
gestaltet (siehe der Kommentarblock oben in der Klasse).

Diese Aktion muss dieselbe Bedingung bekommen. In `EditVehicle`:

```php
/**
 * Loeschen ist erst der zweite Schritt: Ein Fahrzeug im Bestand wird
 * ausgemustert (DeactivateAction in der Liste), und nur ein ausgemustertes
 * laesst sich entfernen. Ohne diese Bedingung waere die Regel genau hier
 * umgehbar - die Liste bietet gar kein Loeschen an, diese Seite schon.
 */
protected function deleteAction(): Action
{
    return parent::deleteAction()
        ->visible(fn (): bool => $this->getRecord()->status === VehicleStatus::Retired);
}
```

Der Test dazu (in dieselbe Testdatei wie oben):

```php
it('hides deleting on the vehicle edit page while the vehicle is in service', function () {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);

    livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->assertActionHidden('delete');
});

it('shows deleting on the vehicle edit page once the vehicle is retired', function () {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Retired]);

    livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->assertActionVisible('delete');
});
```

Prüfen, ob die übrigen fünf Resources ebenfalls eine Löschaktion auf ihrer Bearbeitungsseite
haben, und sie dort genauso absichern:

```bash
grep -rn "deleteAction\|DeleteAction" app/Filament/Resources/*/Pages/
```

- [ ] **Schritt 6: Filter „Status" in die fünf Stammdatenlisten**

Nach dem Vorbild aus `VehiclesTable.php:81-85`, damit inaktive nicht im Weg stehen:

```php
->filters([
    TernaryFilter::make('active')
        ->label('Status')
        ->placeholder('Alle')
        ->trueLabel('Nur aktive')
        ->falseLabel('Nur inaktive')
        // Wie in der Fahrzeugliste: inaktive sind zuschaltbar, aber nicht
        // Standard.
        ->default(true),
])
```

Und eine Kennzeichnung in der Liste, damit ein inaktiver Datensatz auch beim Zuschalten sofort
erkennbar ist:

```php
TextColumn::make('active')
    ->label('Status')
    ->badge()
    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktiv' : 'Inaktiv')
    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
```

Diese Spalte ersetzt die `IconColumn::make('active')` aus Aufgabe 2, Schritt 7 — beim Mitarbeiter
ebenso, damit alle sechs Listen gleich aussehen.

Test dazu, in dieselbe Testdatei:

```php
it('hides inactive records from the default list view but shows them when filtered', function () {
    $active = Employee::factory()->create(['active' => true]);
    $inactive = Employee::factory()->create(['active' => false]);

    livewire(ListEmployees::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive])
        ->filterTable('active', false)
        ->assertCanSeeTableRecords([$inactive]);
});
```

- [ ] **Schritt 6b: Fehlermeldung bei einem belegten Unique-Wert**

`vehicles.license_plate` und `fuel_card_providers.name` sind `unique`, und ein inaktiver Datensatz
belegt den Wert weiterhin (so gewollt, siehe Spec). Ohne Hinweis ist die Meldung „ist bereits
vergeben" aber irreführend, weil der belegende Datensatz in der Standardansicht gar nicht sichtbar
ist — der Benutzer sucht dann nach einem Eintrag, den er nicht findet.

Am Kennzeichen-Feld in `VehicleForm` und am Namensfeld in `FuelCardProviderForm`:

```php
->validationMessages([
    'unique' => 'Dieser Wert ist bereits vergeben — möglicherweise von einem '
        .'ausgemusterten oder inaktiven Eintrag. Über den Statusfilter in der '
        .'Liste sind diese sichtbar.',
])
```

Test dazu:

```php
it('explains that an inactive record may be holding the licence plate', function () {
    Vehicle::factory()->create(['license_plate' => 'M-AB 123', 'status' => VehicleStatus::Retired]);

    livewire(CreateVehicle::class)
        ->fillForm(['license_plate' => 'M-AB 123'])
        ->call('create')
        ->assertHasFormErrors(['license_plate']);
});
```

- [ ] **Schritt 7: Test laufen lassen und bestehen sehen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/DeactivateInsteadOfDeleteTest.php
```

- [ ] **Schritt 8: Bestehende Tests prüfen**

Die vorhandenen Resource-Tests löschen Stammdaten teilweise direkt. Sie schlagen jetzt zu Recht
fehl, weil die Aktion bei aktiven Datensätzen nicht mehr sichtbar ist.

```bash
ddev exec vendor/bin/pest
```

Jeden Fehlschlag einzeln ansehen und **inhaltlich** entscheiden: Test anpassen (Datensatz vorher
inaktiv setzen) — nicht die neue Regel aufweichen, damit ein alter Test wieder grün wird.

- [ ] **Schritt 9: Pint, volle Suite, Commit**

```bash
ddev exec vendor/bin/pint
ddev exec vendor/bin/pest
git add -A
git commit -m "feat: deactivate master data instead of deleting it outright"
```

---

### Aufgabe 4: Inaktive aus Auswahlfeldern, Suche und Anzeige heraushalten

**Dateien:**
- Ändern: `app/Filament/Resources/Vehicles/Schemas/VehicleForm.php` (die vier `Select`-Felder)
- Ändern: `app/Filament/Resources/FuelCards/Schemas/FuelCardForm.php` (Anbieter-Select)
- Ändern: die `*Resource.php` der fünf Stammdaten und `VehicleResource` (globale Suche)
- Ändern: `VehiclesTable`, `VehicleInfolist` (Kennzeichnung „(inaktiv)")
- Test: `tests/Feature/Filament/InactiveMasterDataVisibilityTest.php` (neu)

**Schnittstellen:**
- Verbraucht: `active` aus Aufgabe 2, die Aktionen aus Aufgabe 3.
- Erzeugt: nichts für spätere Aufgaben.

- [ ] **Schritt 1: Den scheiternden Test schreiben**

Datei `tests/Feature/Filament/InactiveMasterDataVisibilityTest.php`:

```php
<?php

use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
});

it('does not offer an inactive employee as a driver', function () {
    $active = Employee::factory()->create(['active' => true, 'last_name' => 'Aktiv']);
    $inactive = Employee::factory()->create(['active' => false, 'last_name' => 'Inaktiv']);
    $vehicle = Vehicle::factory()->create(['employee_id' => null]);

    $options = livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->instance()
        ->form
        ->getComponent('employee_id')
        ->getOptions();

    expect($options)->toHaveKey($active->id)
        ->and($options)->not->toHaveKey($inactive->id);
});

// Der Fallstrick: Wuerde das Auswahlfeld den bereits gesetzten, inzwischen
// inaktiven Fahrer nicht mehr kennen, waere er nach dem naechsten Speichern
// stillschweigend weg.
it('keeps an already assigned employee selectable after they were deactivated', function () {
    $employee = Employee::factory()->create(['active' => true]);
    $vehicle = Vehicle::factory()->for($employee)->create();

    $employee->update(['active' => false]);

    livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($vehicle->fresh()->employee_id)->toBe($employee->id);
});

it('excludes inactive employees from global search', function () {
    Employee::factory()->create(['active' => false, 'last_name' => 'Unsichtbar']);

    $results = App\Filament\Resources\Employees\EmployeeResource::getGlobalSearchResults('Unsichtbar');

    expect($results)->toBeEmpty();
});
```

- [ ] **Schritt 2: Test laufen lassen und scheitern sehen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/InactiveMasterDataVisibilityTest.php
```

Erwartet: Der erste Test scheitert, weil das Auswahlfeld auch den inaktiven Mitarbeiter enthält.

- [ ] **Schritt 3: Auswahlfelder einschränken**

Für jedes `Select`, das auf Stammdaten zeigt (`employee_id`, `dealership_id`,
`leasing_company_id`, `fuel_card_id` im `VehicleForm`; `fuel_card_provider_id` im
`FuelCardForm`):

```php
->relationship(
    name: 'employee',
    titleAttribute: 'last_name',
    // Inaktive gehoeren nicht in eine Neuzuordnung - der bereits gesetzte
    // Wert muss aber in der Liste bleiben, sonst faende das Formular ihn
    // nicht wieder und wuerde die Zuordnung beim naechsten Speichern
    // stillschweigend leeren.
    modifyQueryUsing: fn (Builder $query, ?Model $record) => $query
        ->where('active', true)
        ->orWhere('id', $record?->employee_id),
)
```

Beim Fahrzeug-Select für die Tankkarte den passenden Fremdschlüssel einsetzen. Die genaue
Signatur des vorhandenen `->relationship(...)`-Aufrufs beibehalten und nur `modifyQueryUsing`
ergänzen.

- [ ] **Schritt 4: Globale Suche einschränken**

In jeder der fünf Stammdaten-Resources:

```php
/**
 * Inaktive Stammdaten sollen im Alltag nicht mehr auftauchen - auch nicht
 * ueber die globale Suche, die sonst der einzige Weg waere, sie
 * unbeabsichtigt wieder in Umlauf zu bringen.
 */
public static function getGlobalSearchEloquentQuery(): Builder
{
    return parent::getGlobalSearchEloquentQuery()->where('active', true);
}
```

In `VehicleResource` dasselbe mit `->where('status', VehicleStatus::Active)`.

- [ ] **Schritt 5: Inaktive Bezüge kennzeichnen**

In `VehiclesTable` und `VehicleInfolist` bei der Fahrer-Spalte:

```php
TextColumn::make('employee.full_name')
    ->label('Fahrer')
    ->formatStateUsing(fn (?string $state, Vehicle $record): ?string => match (true) {
        blank($state) => null,
        $record->employee?->active === false => $state.' (inaktiv)',
        default => $state,
    })
```

Dasselbe Muster für Autohaus, Leasinggeber und Tankkarte, wo sie angezeigt werden.

- [ ] **Schritt 6: Test laufen lassen und bestehen sehen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/InactiveMasterDataVisibilityTest.php
```

- [ ] **Schritt 7: Pint, volle Suite, Commit**

```bash
ddev exec vendor/bin/pint
ddev exec vendor/bin/pest
git add -A
git commit -m "feat: keep inactive master data out of pickers, search and lists"
```

---

### Aufgabe 5: Bruttolistenpreis

**Dateien:**
- Neu: `database/migrations/2026_08_05_100001_add_gross_list_price_to_vehicles_table.php`
- Ändern: `app/Models/Vehicle.php` (`$fillable`, `casts()`)
- Ändern: `app/Filament/Resources/Vehicles/Schemas/VehicleForm.php` (Section „Kosten")
- Ändern: die Kostenübersicht (Spalte)
- Test: `tests/Feature/Filament/GrossListPriceTest.php` (neu)

**Schnittstellen:**
- Verbraucht: nichts.
- Erzeugt: Spalte `vehicles.gross_list_price` (`decimal(10,2)`, nullable), Cast `'decimal:2'`.

- [ ] **Schritt 1: Den scheiternden Test schreiben**

Datei `tests/Feature/Filament/GrossListPriceTest.php`:

```php
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

// Dieselbe Absicherung wie bei den uebrigen Kostenfeldern: Das Feld liegt in
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
```

- [ ] **Schritt 2: Test laufen lassen und scheitern sehen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/GrossListPriceTest.php
```

Erwartet: `Unknown column 'gross_list_price'`.

- [ ] **Schritt 3: Migration, Modell, Formular**

Migration:

```php
Schema::table('vehicles', function (Blueprint $table): void {
    // Gleiche Genauigkeit wie die uebrigen Geldfelder in
    // create_vehicles_table. Nullable, weil der Listenpreis bei
    // Bestandsfahrzeugen oft nicht mehr auffindbar ist.
    $table->decimal('gross_list_price', 10, 2)->nullable()->after('annual_insurance');
});
```

`Vehicle`: `'gross_list_price'` in `$fillable`, `'gross_list_price' => 'decimal:2'` in `casts()`.

`VehicleForm`, **innerhalb** der Section „Kosten" (nicht daneben — dort sitzt der Schutz):

```php
TextInput::make('gross_list_price')
    ->label('Bruttolistenpreis')
    ->numeric()
    ->minValue(0)
    ->prefix('€')
    ->helperText('Listenpreis bei Erstzulassung. Fließt nicht in die Jahreskosten ein.')
    ->dehydrated(fn (): bool => auth()->user()?->canViewCosts() ?? false),
```

- [ ] **Schritt 4: Migration ausführen und Test bestehen sehen**

```bash
ddev artisan migrate
ddev exec vendor/bin/pest tests/Feature/Filament/GrossListPriceTest.php
```

- [ ] **Schritt 5: Spalte in die Kostenübersicht**

Die Kostenübersicht finden (`grep -rn "annual_total_cost" app/Filament/`) und die Spalte dort
ergänzen — sortierbar, mit `money('EUR')` wie die Nachbarspalten. **Nicht** in `sumAnnualCost()`
und **nicht** in den Accessor `annual_total_cost` aufnehmen: Ein Listenpreis ist keine laufende
Position. Prüfen, dass die Summenzeile unverändert bleibt.

- [ ] **Schritt 6: Pint, volle Suite, Commit**

```bash
ddev exec vendor/bin/pint
ddev exec vendor/bin/pest
git add -A
git commit -m "feat: add the gross list price behind the cost permission"
```

---

### Aufgabe 6: „Historie" statt „Termine", mit Unfall und Schaden

**Dateien:**
- Ändern: `app/Enums/AppointmentType.php`
- Ändern: `app/Providers/Filament/AdminPanelProvider.php` (Farbe `orange` registrieren)
- Ändern: `app/Filament/Resources/Vehicles/RelationManagers/VehicleAppointmentsRelationManager.php`
- Ändern: alle weiteren Stellen mit dem Wort „Termin" in der Oberfläche
- Test: `tests/Feature/AppointmentTypeTest.php` (neu oder vorhandenen erweitern)

**Schnittstellen:**
- Verbraucht: nichts.
- Erzeugt: `AppointmentType::Accident` (`'accident'`), `AppointmentType::Damage` (`'damage'`).

- [ ] **Schritt 1: Den scheiternden Test schreiben**

```php
<?php

use App\Enums\AppointmentType;

it('offers accident and damage as entry types', function () {
    expect(AppointmentType::Accident->getLabel())->toBe('Unfall')
        ->and(AppointmentType::Damage->getLabel())->toBe('Schaden');
});

it('gives every entry type its own colour', function () {
    $colors = array_map(fn (AppointmentType $type): string => $type->getColor(), AppointmentType::cases());

    expect($colors)->toHaveCount(count(array_unique($colors)));
});
```

- [ ] **Schritt 2: Test laufen lassen und scheitern sehen**

```bash
ddev exec vendor/bin/pest tests/Feature/AppointmentTypeTest.php
```

Erwartet: `Undefined constant App\Enums\AppointmentType::Accident`.

- [ ] **Schritt 3: Enum erweitern**

In `AppointmentType` zwei Fälle ergänzen und in beide `match`-Ausdrücke aufnehmen:

```php
case Accident = 'accident';
case Damage = 'damage';
```

Labels „Unfall" und „Schaden". Farben: `self::Accident => 'danger'`, `self::Damage => 'orange'`.

Den Kommentarblock über `getColor()` anpassen: Er begründet heute, warum `danger` gemieden wird.
Neu soll dort stehen, dass `danger` für den Unfall bewusst gewählt ist, weil er hervorstechen
soll, und dass `orange` zusätzlich registriert werden musste, weil `warning` schon die
Hauptuntersuchung belegt.

- [ ] **Schritt 4: Farbe `orange` im Panel registrieren**

In `AdminPanelProvider::panel()` bei `->colors([...])`:

```php
'orange' => Color::Orange,
```

- [ ] **Schritt 5: Beschriftungen umstellen**

Alle Stellen finden:

```bash
grep -rn "Termin" app/ resources/
```

Umstellen auf „Historie" beziehungsweise „Eintrag":

- Titel des Relation Managers und der Box am Fahrzeug: „Historie"
- Navigation, falls dort ein Eintrag existiert: „Historie"
- Schaltfläche zum Anlegen: „Eintrag hinzufügen"
- Modal-Überschriften und Meldungstexte entsprechend

**Nicht** umbenennen: `VehicleAppointment`, `vehicle_appointments`, `AppointmentType`,
`VehicleAppointmentsRelationManager`, `VehicleAppointmentPolicy`. Nur Oberflächentexte.

- [ ] **Schritt 6: Test laufen lassen, dann die volle Suite**

```bash
ddev exec vendor/bin/pest tests/Feature/AppointmentTypeTest.php
ddev exec vendor/bin/pest
```

Vorhandene Tests, die auf den Text „Termin" prüfen, mit anpassen.

- [ ] **Schritt 7: Pint, Commit**

```bash
ddev exec vendor/bin/pint
git add -A
git commit -m "feat: rename appointments to history and add accident and damage types"
```

---

### Aufgabe 7: 2FA zurücksetzen und 2FA-Pflicht je Benutzer

**Dateien:**
- Neu: `database/migrations/2026_08_05_100002_add_requires_two_factor_to_users_table.php`
- Neu: `app/Http/Middleware/EnsureTwoFactorWhenRequired.php`
- Ändern: `app/Models/User.php`, `app/Policies/UserPolicy.php`
- Ändern: `app/Filament/Resources/Users/Tables/UsersTable.php`,
  `app/Filament/Resources/Users/Schemas/UserForm.php`
- Ändern: `app/Providers/Filament/AdminPanelProvider.php` (`authMiddleware`)
- Test: `tests/Feature/Filament/TwoFactorAdministrationTest.php` (neu)

**Schnittstellen:**
- Verbraucht: nichts.
- Erzeugt: Spalte `users.requires_two_factor` (boolean, Standard `false`);
  `UserPolicy::resetTwoFactor(User $user, User $target): bool`.

- [ ] **Schritt 1: Den scheiternden Test schreiben**

```php
<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

it('lets an admin reset another user two factor setup', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $target = User::factory()->withTwoFactor()->create();

    livewire(ListUsers::class)
        ->callTableAction('resetTwoFactor', $target);

    $target->refresh();

    expect($target->app_authentication_secret)->toBeNull()
        ->and($target->app_authentication_recovery_codes)->toBeNull();
});

it('hides the reset action from a non-admin', function () {
    actingAs(User::factory()->withTwoFactor()->create());

    $target = User::factory()->withTwoFactor()->create();

    livewire(ListUsers::class)
        ->assertTableActionHidden('resetTwoFactor', $target);
});

it('redirects a user who is required to use two factor but has not set it up', function () {
    actingAs(User::factory()->create([
        'requires_two_factor' => true,
        'app_authentication_secret' => null,
    ]));

    get('/admin')->assertRedirect(route('filament.admin.auth.profile'));
});

// Ohne diese Ausnahme leitet die Middleware auf eine Seite um, die sie
// erneut umleitet - der Benutzer koennte 2FA nie einrichten und sich nicht
// einmal abmelden.
it('lets that user reach the profile page and the logout', function () {
    actingAs(User::factory()->create([
        'requires_two_factor' => true,
        'app_authentication_secret' => null,
    ]));

    get(route('filament.admin.auth.profile'))->assertOk();
});

it('does not redirect a user without the requirement', function () {
    actingAs(User::factory()->create([
        'requires_two_factor' => false,
        'app_authentication_secret' => null,
    ]));

    get('/admin')->assertOk();
});

it('does not redirect a user who has the requirement and already set two factor up', function () {
    actingAs(User::factory()->withTwoFactor()->create(['requires_two_factor' => true]));

    get('/admin')->assertOk();
});
```

- [ ] **Schritt 2: Test laufen lassen und scheitern sehen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/TwoFactorAdministrationTest.php
```

Erwartet: `Unknown column 'requires_two_factor'`.

- [ ] **Schritt 3: Migration und Modell**

```php
Schema::table('users', function (Blueprint $table): void {
    // Standard false: Die Pflicht ist eine bewusste Anordnung, kein
    // Nebeneffekt einer Migration - sonst sperrt das naechste Deployment
    // alle Benutzer aus, bis sie 2FA eingerichtet haben.
    $table->boolean('requires_two_factor')->default(false)->after('can_view_costs');
});
```

`User`: `'requires_two_factor'` in `$fillable`, `'requires_two_factor' => 'boolean'` in `casts()`,
`'requires_two_factor' => false` in `$attributes`.

- [ ] **Schritt 4: Policy-Methode**

In `UserPolicy`:

```php
/**
 * Ein Admin kann fremdes 2FA nur entfernen, nie einrichten - das Geheimnis
 * darf ausschliesslich in der Authenticator-App des Benutzers entstehen.
 */
public function resetTwoFactor(User $user, User $target): bool
{
    return $user->isAdmin();
}
```

- [ ] **Schritt 5: Aktion und Schalter in der Benutzerverwaltung**

In `UsersTable` bei `recordActions()`:

```php
Action::make('resetTwoFactor')
    ->label('2FA zurücksetzen')
    ->icon('heroicon-m-shield-exclamation')
    ->color('warning')
    ->requiresConfirmation()
    ->modalHeading('Zwei-Faktor-Authentifizierung zurücksetzen')
    ->modalDescription(
        'Der Benutzer muss die Authentifizierung danach neu einrichten. '
        .'Die bisherigen Wiederherstellungscodes werden ungültig.'
    )
    // Nur sinnvoll, wenn ueberhaupt etwas eingerichtet ist.
    ->visible(fn (User $record): bool => filled($record->app_authentication_secret)
        && auth()->user()->can('resetTwoFactor', $record))
    ->action(function (User $record): void {
        $record->forceFill([
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ])->save();

        Notification::make()
            ->success()
            ->title('2FA zurückgesetzt')
            ->send();
    }),
```

In `UserForm`:

```php
Toggle::make('requires_two_factor')
    ->label('2FA verpflichtend')
    ->helperText('Der Benutzer wird bis zur Einrichtung auf die Profilseite geleitet.'),
```

In `UsersTable` zusätzlich `IconColumn::make('requires_two_factor')->label('2FA Pflicht')->boolean()`.

- [ ] **Schritt 6: Middleware**

`app/Http/Middleware/EnsureTwoFactorWhenRequired.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Erzwingt die 2FA-Einrichtung fuer Benutzer mit requires_two_factor.
 *
 * Warum eigene Middleware statt Filaments isRequired: Dessen Wert wird beim
 * Registrieren der Routen ausgewertet (Filament\Pages\Concerns\HasRoutes
 * entscheidet dort, ob die eingebaute Middleware ueberhaupt an die Route
 * gehaengt wird). Zu diesem Zeitpunkt ist kein Benutzer angemeldet, eine
 * benutzerabhaengige Entscheidung ist dort also nicht moeglich.
 */
class EnsureTwoFactorWhenRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $user->requires_two_factor || filled($user->app_authentication_secret)) {
            return $next($request);
        }

        // Das Ziel der Umleitung und der Abmeldeweg muessen erreichbar
        // bleiben, sonst leitet die Middleware auf eine Seite um, die sie
        // erneut umleitet. Livewire-Requests laufen ueber eine eigene Route
        // und tragen das Formular der Profilseite - wuerden sie umgeleitet,
        // koennte der Benutzer die Einrichtung nicht absenden.
        if ($request->routeIs('filament.admin.auth.profile', 'filament.admin.auth.logout')
            || $request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        return redirect()->route('filament.admin.auth.profile');
    }
}
```

In `AdminPanelProvider`:

```php
->authMiddleware([
    Authenticate::class,
    EnsureTwoFactorWhenRequired::class,
]);
```

- [ ] **Schritt 7: Test laufen lassen und bestehen sehen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/TwoFactorAdministrationTest.php
```

- [ ] **Schritt 8: Von Hand gegenprüfen, dass die Schleife nicht existiert**

Einen Testbenutzer mit `requires_two_factor = true` anlegen — **in der Testdatenbank oder im
Docker-Compose-Stack, nicht in der Entwicklungsdatenbank** — anmelden, und prüfen: Die
Profilseite ist erreichbar, 2FA lässt sich dort tatsächlich einrichten, und danach ist das Panel
normal benutzbar. Ein automatisierter Test kann das Absenden des Einrichtungsformulars nur
begrenzt nachstellen.

- [ ] **Schritt 9: Pint, volle Suite, Commit**

```bash
ddev exec vendor/bin/pint
ddev exec vendor/bin/pest
git add -A
git commit -m "feat: let admins reset and require two-factor authentication"
```

---

### Abschluss

- [ ] **CLAUDE.md ergänzen**

Unter „Was beim Ändern leicht übersehen wird" aufnehmen:

- Löschen ist an den Zustand gebunden: `GuardedDeleteAction` ist nur bei inaktiven Datensätzen
  sichtbar. Wer eine Löschaktion neu einbaut, muss diese Bedingung mitnehmen, sonst ist die Regel
  an dieser Stelle aufgehoben.
- Auswahlfelder auf Stammdaten filtern auf `active`, **einschließlich** des bereits gesetzten
  Wertes. Ohne diese Ausnahme leert das Speichern eine Zuordnung auf einen inaktiven Datensatz.
- Der Bruttolistenpreis gehört wie die übrigen Kostenfelder in die Section „Kosten" — außerhalb
  wäre er ungeschützt.

- [ ] **Backlog aufräumen**

In `docs/backlog.md` die Beobachtung streichen, dass die Stammdatenlisten nicht alle Felder
zeigen, falls sie durch die neue Statusspalte erledigt ist.

- [ ] **Branch pushen und PR öffnen**

```bash
git push -u origin feature/archiving-and-admin-controls
gh pr create --base main --title "feat: deactivation, two-factor administration, list price and history" \
  --body "Beschreibung: die vier Punkte aus der Spec, je ein Absatz, mit der
Begründung gegen SoftDeletes und dem Ergebnis der vollen Testsuite."
```
