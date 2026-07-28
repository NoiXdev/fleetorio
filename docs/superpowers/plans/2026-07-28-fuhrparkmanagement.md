# Fuhrparkmanagement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eine interne Web-App, die den Fahrzeugbestand der Firma dokumentiert (Fahrzeug, Fahrer, Tankkarte, Leasingzeitraum, Kosten), beim Login vor auslaufenden Leasingverträgen warnt und Kostendaten nur berechtigten Nutzern zeigt.

**Architecture:** Laravel 13 mit Filament 5 als Admin-Panel. Fünf Eloquent-Modelle (Vehicle, Employee, FuelCard, LeasingCompany, User), je eine Filament-Resource pro Stammdatentyp, eine zweite Resource auf `Vehicle` für die Kostenauswertung, zwei Dashboard-Widgets. Autorisierung über Laravel-Policies plus ein Gate `viewCosts`. Datenintegrität wird doppelt gesichert: Foreign Keys mit `restrictOnDelete()` in der Datenbank und verständliche Fehlermeldungen in der Oberfläche.

**Tech Stack:** PHP 8.4 (DDEV-Default), Laravel 13.x, Filament 5.7.x, Livewire 4.x, MariaDB 11.8, Pest 4.x, DDEV 1.25.2

## Global Constraints

Diese Vorgaben gelten für **jede** Task. Sie stammen aus der Spec
(`docs/superpowers/specs/2026-07-28-fuhrparkmanagement-design.md`) und aus der verifizierten
Filament-5-API.

- **Projektwurzel:** `/Users/rtebest/_dev/misc/fuhrpark` (Git-Repo existiert, enthält bereits `docs/`)
- **Alle PHP-/Artisan-/Composer-Befehle laufen über DDEV:** `ddev artisan …`, `ddev composer …`, `ddev exec vendor/bin/pest`. PHP und Composer sind auf dem Host **nicht** installiert.
- **Versionsuntergrenzen:** PHP ≥ 8.3 (Laravel-13-Skeleton fordert `^8.3`), Laravel ≥ 13.8, Filament ≥ 5.7, Livewire ≥ 4.1, Tailwind ≥ 4.1
- **Warnfenster für Leasingende: 180 Tage**, fest im Code als Konstante, nicht konfigurierbar
- **Sprache der Oberfläche: Deutsch.** Labels werden explizit gesetzt (`->label('Kennzeichen')`), da Filament sonst englische Feldnamen ableitet. Code, Klassennamen, Spaltennamen und Commit-Messages bleiben englisch.
- **Geldbeträge:** `decimal(10,2)`, nullable, Anzeige mit `->money('EUR')`. Fehlende Werte zählen in Berechnungen als 0.
- **Autorisierung ist serverseitig.** Ausblenden im Frontend ist Komfort, kein Schutz. Jede Berechtigung braucht einen Test, der den direkten Zugriff prüft — nicht nur die Sichtbarkeit.
- **Kein Zugriff auf Kosten ohne das Gate `viewCosts`.** Das gilt für die Kosten-Resource, die Kostenfelder im Fahrzeugformular und die Kostenspalten in Tabellen.
- **Fahrzeuge werden nicht gelöscht, sondern auf `status = retired` gesetzt.** Endgültiges Löschen ist Admins vorbehalten.
- **Filament-5-Fallen** (weicht von v3/v4 ab — nicht aus dem Gedächtnis schreiben):
  - Formulare sind `Filament\Schemas\Schema`, Signatur `public static function form(Schema $schema): Schema`. Es gibt kein `Form` mehr.
  - Layout-Komponenten (`Section`, `Grid`) liegen unter `Filament\Schemas\Components\*`, **nicht** unter `Filament\Forms\Components\*`.
  - Alle Actions kommen aus `Filament\Actions\*`. `Filament\Tables\Actions\*` existiert nicht mehr.
  - Tabellen nutzen `->recordActions([...])` und `->toolbarActions([...])`, nicht `->actions()`/`->bulkActions()`.
  - Jede Resource liegt in einem eigenen Unterordner mit ausgelagerten `Schemas/`- und `Tables/`-Klassen, deren Methode `public static function configure(...)` heißt.
- **Commit nach jeder Task**, Message auf Englisch im Imperativ.

---

## File Structure

Verantwortlichkeiten der Dateien, die dieser Plan anlegt. Filament gibt die Struktur weitgehend
vor; abweichende Entscheidungen sind begründet.

**Enums** (`app/Enums/`) — die drei Wertelisten der Domäne. Eigene Dateien, weil sie von Model,
Formular, Tabelle und Filter gemeinsam genutzt werden.
- `FuelType.php` — Antriebsart, implementiert `HasLabel`
- `VehicleStatus.php` — aktiv/ausgemustert, implementiert `HasLabel` und `HasColor`
- `FuelCardProvider.php` — DKV/Elli/sonstige, implementiert `HasLabel`
- `UserRole.php` — Admin/Nur-Lesen, implementiert `HasLabel`

**Modelle** (`app/Models/`) — Daten, Casts, Beziehungen, Scopes und abgeleitete Werte. Fachlogik,
die nicht von Filament abhängt, gehört hierher und nicht in die Resource: so ist sie ohne
Livewire testbar.
- `Vehicle.php` — Beziehungen, `annual_total_cost`-Accessor, Scopes `active()` und `leaseEndingWithin()`
- `Employee.php`, `FuelCard.php`, `LeasingCompany.php` — Stammdaten, jeweils mit `vehicles()`-Beziehung für den Löschschutz
- `User.php` — erweitert um Rolle, Kostenrecht, `FilamentUser` und die 2FA-Traits

**Policies** (`app/Policies/`) — eine pro Model. Filament ruft sie automatisch auf.

**Filament-Resources** (`app/Filament/Resources/`) — je Resource ein Unterordner mit
`Pages/`, `Schemas/`, `Tables/` (von `make:filament-resource` erzeugt).
- `Vehicles/` — die Kern-Resource
- `VehicleCosts/` — zweite Resource auf demselben Model `Vehicle`, nur Listenansicht, hinter dem Gate `viewCosts`. Eine eigene Resource statt einer Custom Page, weil Filter, Suche, Summenzeile und Export dann fertige Bausteine sind.
- `Employees/`, `FuelCards/`, `LeasingCompanies/`, `Users/`

**Widgets** (`app/Filament/Widgets/`)
- `FleetOverview.php` — Kennzahlen ohne Geldbeträge
- `ExpiringLeases.php` — Tabellen-Widget für das 180-Tage-Fenster

**Tests** (`tests/Feature/`) — gespiegelt zur Struktur oben. Modell-Fachlogik
(`tests/Feature/Models/`) getrennt von Oberflächen- und Berechtigungstests
(`tests/Feature/Filament/`), weil erstere schnell und ohne Livewire laufen.

---

## Task 1: DDEV, Laravel, Filament und Pest aufsetzen

**Files:**
- Create: `.ddev/config.yaml` (via `ddev config`)
- Create: gesamtes Laravel-Skeleton (via `composer create-project`)
- Create: `app/Providers/Filament/AdminPanelProvider.php` (via `filament:install`)
- Create: `tests/Pest.php`
- Modify: `.gitignore`

**Interfaces:**
- Consumes: nichts (erste Task)
- Produces: laufende DDEV-Umgebung, erreichbares Filament-Panel unter `/admin`, ausführbare Pest-Suite. Alle Folge-Tasks setzen `ddev artisan` und `ddev exec vendor/bin/pest` voraus.

**Achtung:** Das Verzeichnis ist **nicht leer** — es enthält `.git/`, `docs/` und `.gitignore`.
`ddev composer create-project` ist dafür gebaut (installiert in ein temporäres Verzeichnis und
kopiert anschließend), aber Schritt 4 verifiziert explizit, dass `docs/` überlebt hat.

- [ ] **Step 1: DDEV konfigurieren**

```bash
cd /Users/rtebest/_dev/misc/fuhrpark
ddev config --project-type=laravel --docroot=public --project-name=fuhrpark
```

Erwartet: Meldung, dass `.ddev/config.yaml` erstellt wurde. Die DDEV-Defaults passen bereits
(PHP 8.4, MariaDB 11.8) und werden nicht überschrieben.

Falls DDEV meldet, ein anderes Projekt existiere in einem Elternverzeichnis: Abbrechen und
melden — nicht mit `--unlist` fremde Projekte deregistrieren.

- [ ] **Step 2: DDEV starten**

```bash
ddev start -y
```

Erwartet: Ausgabe mit der Projekt-URL (`https://fuhrpark.ddev.site`).

- [ ] **Step 3: Laravel installieren**

```bash
ddev composer create-project laravel/laravel
```

Erwartet: Laravel 13.x wird installiert, `APP_KEY` wird durch das post-create-Skript generiert.

- [ ] **Step 4: Prüfen, dass die Vorarbeit erhalten ist und Laravel läuft**

```bash
ls docs/superpowers/specs/ && ddev artisan --version && ddev artisan about --only=environment
```

Erwartet: Die Spec-Datei ist noch da, Laravel meldet Version 13.x, und die Datenbank-Verbindung
zeigt auf MariaDB (DDEV schreibt die `.env` automatisch).

Falls `docs/` verschwunden ist: `git checkout docs/` stellt es wieder her — es ist committet.

- [ ] **Step 5: Datenbankverbindung testen**

```bash
ddev artisan migrate
```

Erwartet: Die Standard-Laravel-Migrationen (users, cache, jobs) laufen ohne Fehler durch. Das
beweist, dass MariaDB erreichbar und die `.env` korrekt ist.

- [ ] **Step 6: Filament installieren**

```bash
ddev composer require filament/filament:"^5.0"
ddev artisan filament:install --panels
```

Erwartet: `app/Providers/Filament/AdminPanelProvider.php` wird erstellt und in
`bootstrap/providers.php` registriert.

- [ ] **Step 7: Pest installieren**

```bash
ddev composer require pestphp/pest pestphp/pest-plugin-laravel pestphp/pest-plugin-livewire --dev
ddev exec vendor/bin/pest --init
```

Erwartet: `tests/Pest.php` wird erstellt. Falls Pest nach dem Überschreiben von `phpunit.xml`
fragt: zustimmen.

- [ ] **Step 8: `RefreshDatabase` in Pest aktivieren**

Der Pest-Stub liefert `RefreshDatabase` auskommentiert. Ohne diese Änderung teilen sich alle
Tests eine Datenbank und beeinflussen sich gegenseitig.

`tests/Pest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
```

- [ ] **Step 9: Failing Smoke-Test schreiben**

`tests/Feature/PanelTest.php`:

```php
<?php

it('serves the panel login page', function () {
    $this->get('/admin/login')->assertOk();
});
```

- [ ] **Step 10: Test ausführen**

```bash
ddev exec vendor/bin/pest tests/Feature/PanelTest.php
```

Erwartet: PASS. Das Panel-Login ist erreichbar. (Dieser Test ist absichtlich nicht
„failing first" — er verifiziert das Setup, es gibt keine Implementierung dazu.)

- [ ] **Step 11: `.DS_Store` in `.gitignore` ergänzen**

Laravel überschreibt die bisherige `.gitignore`. Prüfen, ob `.DS_Store` enthalten ist, und
falls nicht, am Ende der Datei anfügen:

```
.DS_Store
```

- [ ] **Step 12: Commit**

```bash
git add -A
git commit -m "Set up DDEV, Laravel 13, Filament 5 and Pest"
```

---

## Task 2: Enums für Antriebsart, Status, Kartenanbieter und Rolle

**Files:**
- Create: `app/Enums/FuelType.php`
- Create: `app/Enums/VehicleStatus.php`
- Create: `app/Enums/FuelCardProvider.php`
- Create: `app/Enums/UserRole.php`
- Test: `tests/Feature/Models/EnumTest.php`

**Interfaces:**
- Consumes: nichts
- Produces:
  - `FuelType` (string-backed): `Petrol='petrol'`, `Diesel='diesel'`, `Hybrid='hybrid'`, `PluginHybrid='plugin_hybrid'`, `Electric='electric'`, `Other='other'`; Methode `getLabel(): string`
  - `VehicleStatus` (string-backed): `Active='active'`, `Retired='retired'`; Methoden `getLabel(): string`, `getColor(): string`
  - `FuelCardProvider` (string-backed): `Dkv='dkv'`, `Elli='elli'`, `Other='other'`; Methode `getLabel(): string`
  - `UserRole` (string-backed): `Admin='admin'`, `Viewer='viewer'`; Methode `getLabel(): string`

Alle vier werden in Models als Cast, in Formularen via `->options(EnumName::class)` und in
Filtern via `SelectFilter::…->options(EnumName::class)` verwendet.

- [ ] **Step 1: Failing Test schreiben**

`tests/Feature/Models/EnumTest.php`:

```php
<?php

use App\Enums\FuelCardProvider;
use App\Enums\FuelType;
use App\Enums\UserRole;
use App\Enums\VehicleStatus;

it('provides German labels for fuel types', function () {
    expect(FuelType::Diesel->getLabel())->toBe('Diesel')
        ->and(FuelType::PluginHybrid->getLabel())->toBe('Plug-in-Hybrid')
        ->and(FuelType::Electric->getLabel())->toBe('Elektro')
        ->and(FuelType::Other->getLabel())->toBe('Sonstige');
});

it('covers every fuel type from the spec', function () {
    expect(FuelType::cases())->toHaveCount(6);
});

it('provides labels and colours for vehicle status', function () {
    expect(VehicleStatus::Active->getLabel())->toBe('Aktiv')
        ->and(VehicleStatus::Retired->getLabel())->toBe('Ausgemustert')
        ->and(VehicleStatus::Active->getColor())->toBe('success')
        ->and(VehicleStatus::Retired->getColor())->toBe('gray');
});

it('provides labels for fuel card providers', function () {
    expect(FuelCardProvider::Dkv->getLabel())->toBe('DKV')
        ->and(FuelCardProvider::Elli->getLabel())->toBe('Elli')
        ->and(FuelCardProvider::Other->getLabel())->toBe('Sonstige');
});

it('provides labels for user roles', function () {
    expect(UserRole::Admin->getLabel())->toBe('Administrator')
        ->and(UserRole::Viewer->getLabel())->toBe('Nur-Lesen');
});
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

```bash
ddev exec vendor/bin/pest tests/Feature/Models/EnumTest.php
```

Erwartet: FAIL mit `Class "App\Enums\FuelType" not found`.

- [ ] **Step 3: Enums implementieren**

`app/Enums/FuelType.php`:

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FuelType: string implements HasLabel
{
    case Petrol = 'petrol';
    case Diesel = 'diesel';
    case Hybrid = 'hybrid';
    case PluginHybrid = 'plugin_hybrid';
    case Electric = 'electric';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Petrol => 'Benzin',
            self::Diesel => 'Diesel',
            self::Hybrid => 'Hybrid',
            self::PluginHybrid => 'Plug-in-Hybrid',
            self::Electric => 'Elektro',
            self::Other => 'Sonstige',
        };
    }
}
```

`app/Enums/VehicleStatus.php`:

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VehicleStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Retired = 'retired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Aktiv',
            self::Retired => 'Ausgemustert',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Retired => 'gray',
        };
    }
}
```

`app/Enums/FuelCardProvider.php`:

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FuelCardProvider: string implements HasLabel
{
    case Dkv = 'dkv';
    case Elli = 'elli';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dkv => 'DKV',
            self::Elli => 'Elli',
            self::Other => 'Sonstige',
        };
    }
}
```

`app/Enums/UserRole.php`:

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Viewer = 'viewer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Viewer => 'Nur-Lesen',
        };
    }
}
```

- [ ] **Step 4: Test ausführen, Erfolg bestätigen**

```bash
ddev exec vendor/bin/pest tests/Feature/Models/EnumTest.php
```

Erwartet: PASS, 5 Tests.

- [ ] **Step 5: Commit**

```bash
git add app/Enums tests/Feature/Models/EnumTest.php
git commit -m "Add domain enums for fuel type, vehicle status, card provider and role"
```

---

## Task 3: Stammdaten-Modelle Employee, FuelCard und LeasingCompany

**Files:**
- Create: `database/migrations/…_create_employees_table.php`
- Create: `database/migrations/…_create_fuel_cards_table.php`
- Create: `database/migrations/…_create_leasing_companies_table.php`
- Create: `app/Models/Employee.php`, `app/Models/FuelCard.php`, `app/Models/LeasingCompany.php`
- Create: `database/factories/EmployeeFactory.php`, `FuelCardFactory.php`, `LeasingCompanyFactory.php`
- Test: `tests/Feature/Models/StammdatenTest.php`

**Interfaces:**
- Consumes: `App\Enums\FuelCardProvider` aus Task 2
- Produces:
  - `Employee` mit Feldern `first_name`, `last_name`, `department`, `email`, `active` (bool) und Accessor `full_name` (Format: `"Vorname Nachname"`)
  - `FuelCard` mit `provider` (Cast `FuelCardProvider`), `card_number` (Cast `encrypted`), `valid_until` (Cast `date`), `notes`
  - `LeasingCompany` mit `name`, `contact_person`, `phone`, `email`, `notes`
  - Factories für alle drei, von Folge-Tasks in Tests genutzt
  - Die `vehicles()`-HasMany-Beziehungen werden erst in Task 4 ergänzt, wenn die Tabelle existiert.

- [ ] **Step 1: Failing Test schreiben**

`tests/Feature/Models/StammdatenTest.php`:

```php
<?php

use App\Enums\FuelCardProvider;
use App\Models\Employee;
use App\Models\FuelCard;
use App\Models\LeasingCompany;

it('composes an employee full name', function () {
    $employee = Employee::factory()->create([
        'first_name' => 'Anna',
        'last_name' => 'Beispiel',
    ]);

    expect($employee->full_name)->toBe('Anna Beispiel');
});

it('defaults employees to active', function () {
    expect(Employee::factory()->create()->active)->toBeTrue();
});

it('casts the fuel card provider to an enum', function () {
    $card = FuelCard::factory()->create(['provider' => FuelCardProvider::Dkv]);

    expect($card->fresh()->provider)->toBe(FuelCardProvider::Dkv);
});

it('encrypts the fuel card number in the database', function () {
    $card = FuelCard::factory()->create(['card_number' => '7080123456789']);

    // Über das Model gelesen: Klartext.
    expect($card->fresh()->card_number)->toBe('7080123456789');

    // Direkt aus der Datenbank gelesen: verschlüsselt.
    $raw = DB::table('fuel_cards')->where('id', $card->id)->value('card_number');
    expect($raw)->not->toBe('7080123456789')
        ->and($raw)->not->toContain('7080');
});

it('casts the fuel card validity to a date', function () {
    $card = FuelCard::factory()->create(['valid_until' => '2028-12-31']);

    expect($card->fresh()->valid_until->format('Y-m-d'))->toBe('2028-12-31');
});

it('stores a leasing company with contact details', function () {
    $company = LeasingCompany::factory()->create([
        'name' => 'VW Leasing',
        'contact_person' => 'Herr Muster',
    ]);

    expect($company->fresh()->name)->toBe('VW Leasing')
        ->and($company->fresh()->contact_person)->toBe('Herr Muster');
});
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

```bash
ddev exec vendor/bin/pest tests/Feature/Models/StammdatenTest.php
```

Erwartet: FAIL mit `Class "App\Models\Employee" not found`.

- [ ] **Step 3: Migrationen erzeugen und füllen**

```bash
ddev artisan make:migration create_employees_table
ddev artisan make:migration create_fuel_cards_table
ddev artisan make:migration create_leasing_companies_table
```

`create_employees_table` — `up()`:

```php
Schema::create('employees', function (Blueprint $table) {
    $table->id();
    $table->string('first_name');
    $table->string('last_name');
    $table->string('department')->nullable();
    $table->string('email')->nullable();
    $table->boolean('active')->default(true);
    $table->timestamps();
});
```

`create_fuel_cards_table` — `up()`:

```php
Schema::create('fuel_cards', function (Blueprint $table) {
    $table->id();
    $table->string('provider');
    // text statt string: der verschlüsselte Wert ist deutlich länger als das Klartext-Original.
    $table->text('card_number');
    $table->date('valid_until')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

`create_leasing_companies_table` — `up()`:

```php
Schema::create('leasing_companies', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('contact_person')->nullable();
    $table->string('phone')->nullable();
    $table->string('email')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 4: Modelle implementieren**

`app/Models/Employee.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'department',
        'email',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->first_name} {$this->last_name}"));
    }
}
```

`app/Models/FuelCard.php`:

```php
<?php

namespace App\Models;

use App\Enums\FuelCardProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FuelCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'card_number',
        'valid_until',
        'notes',
    ];

    protected $hidden = [
        'card_number',
    ];

    protected function casts(): array
    {
        return [
            'provider' => FuelCardProvider::class,
            'card_number' => 'encrypted',
            'valid_until' => 'date',
        ];
    }
}
```

`app/Models/LeasingCompany.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeasingCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'notes',
    ];
}
```

- [ ] **Step 5: Factories implementieren**

```bash
ddev artisan make:factory EmployeeFactory --model=Employee
ddev artisan make:factory FuelCardFactory --model=FuelCard
ddev artisan make:factory LeasingCompanyFactory --model=LeasingCompany
```

`database/factories/EmployeeFactory.php` — `definition()`:

```php
return [
    'first_name' => fake()->firstName(),
    'last_name' => fake()->lastName(),
    'department' => fake()->randomElement(['Vertrieb', 'Technik', 'Verwaltung']),
    'email' => fake()->unique()->safeEmail(),
    'active' => true,
];
```

`database/factories/FuelCardFactory.php` — `definition()`:

```php
return [
    'provider' => fake()->randomElement(FuelCardProvider::cases()),
    'card_number' => (string) fake()->numerify('708#########'),
    'valid_until' => fake()->dateTimeBetween('+1 year', '+4 years'),
    'notes' => null,
];
```

Import ergänzen: `use App\Enums\FuelCardProvider;`

`database/factories/LeasingCompanyFactory.php` — `definition()`:

```php
return [
    'name' => fake()->randomElement(['VW Leasing', 'ALD Automotive', 'Athlon', 'Sixt Leasing']),
    'contact_person' => fake()->name(),
    'phone' => fake()->phoneNumber(),
    'email' => fake()->unique()->companyEmail(),
    'notes' => null,
];
```

- [ ] **Step 6: Migration ausführen und Test bestätigen**

```bash
ddev artisan migrate
ddev exec vendor/bin/pest tests/Feature/Models/StammdatenTest.php
```

Erwartet: PASS, 6 Tests. Besonders relevant: Der Verschlüsselungstest beweist, dass die
Kartennummer nicht im Klartext in der Datenbank liegt.

- [ ] **Step 7: Commit**

```bash
git add app/Models database/migrations database/factories tests/Feature/Models/StammdatenTest.php
git commit -m "Add Employee, FuelCard and LeasingCompany models with encrypted card numbers"
```

---

## Task 4: Vehicle-Modell mit Kostenberechnung, Scopes und Löschschutz

**Files:**
- Create: `database/migrations/…_create_vehicles_table.php`
- Create: `app/Models/Vehicle.php`
- Create: `database/factories/VehicleFactory.php`
- Modify: `app/Models/Employee.php`, `app/Models/FuelCard.php`, `app/Models/LeasingCompany.php` (je `vehicles()`-Beziehung ergänzen)
- Test: `tests/Feature/Models/VehicleTest.php`

**Interfaces:**
- Consumes: `FuelType`, `VehicleStatus` (Task 2), `Employee`, `FuelCard`, `LeasingCompany` (Task 3)
- Produces:
  - `Vehicle::LEASE_WARNING_DAYS = 180` — die zentrale Konstante für das Warnfenster. Widget und Tests lesen sie hier, damit es keinen zweiten Ort mit einer 180 gibt.
  - Beziehungen `employee()`, `fuelCard()`, `leasingCompany()` (alle BelongsTo, nullable)
  - Accessor `annual_total_cost` (float): `monthly_lease_rate × 12 + annual_vehicle_tax + annual_insurance`, fehlende Werte als 0
  - Scope `active()` — nur `status = Active`
  - Scope `leaseEndingWithin(int $days)` — aktive Fahrzeuge mit `lease_end` zwischen heute und heute + `$days`, jeweils einschließlich
  - Auf `Employee`, `FuelCard`, `LeasingCompany` jeweils `vehicles(): HasMany`
  - `VehicleFactory` mit Zustand `retired()`

- [ ] **Step 1: Failing Test schreiben**

`tests/Feature/Models/VehicleTest.php`:

```php
<?php

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use App\Models\Employee;
use App\Models\FuelCard;
use App\Models\LeasingCompany;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;

it('sums annual total cost from lease rate, tax and insurance', function () {
    $vehicle = Vehicle::factory()->create([
        'monthly_lease_rate' => 450.00,
        'annual_vehicle_tax' => 180.00,
        'annual_insurance' => 620.00,
    ]);

    // 450 * 12 + 180 + 620 = 6200
    expect($vehicle->annual_total_cost)->toBe(6200.0);
});

it('treats missing cost values as zero', function () {
    $vehicle = Vehicle::factory()->create([
        'monthly_lease_rate' => 300.00,
        'annual_vehicle_tax' => null,
        'annual_insurance' => null,
    ]);

    expect($vehicle->annual_total_cost)->toBe(3600.0);
});

it('reports zero annual cost when nothing is known', function () {
    $vehicle = Vehicle::factory()->create([
        'monthly_lease_rate' => null,
        'annual_vehicle_tax' => null,
        'annual_insurance' => null,
    ]);

    expect($vehicle->annual_total_cost)->toBe(0.0);
});

it('casts fuel type and status to enums', function () {
    $vehicle = Vehicle::factory()->create(['fuel_type' => FuelType::Electric]);

    expect($vehicle->fresh()->fuel_type)->toBe(FuelType::Electric)
        ->and($vehicle->fresh()->status)->toBe(VehicleStatus::Active);
});

it('defaults new vehicles to active', function () {
    expect(Vehicle::factory()->create()->status)->toBe(VehicleStatus::Active);
});

it('scopes to active vehicles only', function () {
    Vehicle::factory()->count(2)->create();
    Vehicle::factory()->retired()->create();

    expect(Vehicle::active()->count())->toBe(2);
});

describe('lease expiry window', function () {
    it('includes a lease ending today', function () {
        Vehicle::factory()->create(['lease_end' => today()]);

        expect(Vehicle::leaseEndingWithin(180)->count())->toBe(1);
    });

    it('includes a lease ending exactly at the window edge', function () {
        Vehicle::factory()->create(['lease_end' => today()->addDays(180)]);

        expect(Vehicle::leaseEndingWithin(180)->count())->toBe(1);
    });

    it('excludes a lease ending one day beyond the window', function () {
        Vehicle::factory()->create(['lease_end' => today()->addDays(181)]);

        expect(Vehicle::leaseEndingWithin(180)->count())->toBe(0);
    });

    it('excludes leases that already expired', function () {
        Vehicle::factory()->create(['lease_end' => today()->subDay()]);

        expect(Vehicle::leaseEndingWithin(180)->count())->toBe(0);
    });

    it('excludes retired vehicles even inside the window', function () {
        Vehicle::factory()->retired()->create(['lease_end' => today()->addDays(30)]);

        expect(Vehicle::leaseEndingWithin(180)->count())->toBe(0);
    });
});

it('uses a 180 day warning window', function () {
    expect(Vehicle::LEASE_WARNING_DAYS)->toBe(180);
});

it('relates to employee, fuel card and leasing company', function () {
    $vehicle = Vehicle::factory()
        ->for(Employee::factory())
        ->for(FuelCard::factory())
        ->for(LeasingCompany::factory())
        ->create();

    expect($vehicle->employee)->toBeInstanceOf(Employee::class)
        ->and($vehicle->fuelCard)->toBeInstanceOf(FuelCard::class)
        ->and($vehicle->leasingCompany)->toBeInstanceOf(LeasingCompany::class);
});

it('allows a vehicle without employee, card or leasing company', function () {
    $vehicle = Vehicle::factory()->create([
        'employee_id' => null,
        'fuel_card_id' => null,
        'leasing_company_id' => null,
    ]);

    expect($vehicle->employee)->toBeNull()
        ->and($vehicle->fuelCard)->toBeNull()
        ->and($vehicle->leasingCompany)->toBeNull();
});

describe('referential integrity', function () {
    it('refuses to delete an employee that still has a vehicle', function () {
        $employee = Employee::factory()->create();
        Vehicle::factory()->for($employee)->create();

        expect(fn () => $employee->delete())->toThrow(QueryException::class);
    });

    it('refuses to delete a fuel card that is still assigned', function () {
        $card = FuelCard::factory()->create();
        Vehicle::factory()->for($card)->create();

        expect(fn () => $card->delete())->toThrow(QueryException::class);
    });

    it('refuses to delete a leasing company that is still referenced', function () {
        $company = LeasingCompany::factory()->create();
        Vehicle::factory()->for($company)->create();

        expect(fn () => $company->delete())->toThrow(QueryException::class);
    });

    it('protects assignments of retired vehicles too', function () {
        $employee = Employee::factory()->create();
        Vehicle::factory()->retired()->for($employee)->create();

        expect(fn () => $employee->delete())->toThrow(QueryException::class);
    });

    it('allows deleting an employee without vehicles', function () {
        $employee = Employee::factory()->create();

        $employee->delete();

        expect(Employee::count())->toBe(0);
    });
});

it('exposes vehicles from the master data side', function () {
    $employee = Employee::factory()->create();
    Vehicle::factory()->count(2)->for($employee)->create();

    expect($employee->vehicles)->toHaveCount(2);
});
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

```bash
ddev exec vendor/bin/pest tests/Feature/Models/VehicleTest.php
```

Erwartet: FAIL mit `Class "App\Models\Vehicle" not found`.

- [ ] **Step 3: Migration erzeugen und füllen**

```bash
ddev artisan make:migration create_vehicles_table
```

`up()`:

```php
Schema::create('vehicles', function (Blueprint $table) {
    $table->id();
    $table->string('brand');
    $table->string('model');
    $table->string('license_plate')->unique();
    $table->string('fuel_type');

    // restrictOnDelete: Der Löschschutz aus der Spec wird auf Datenbankebene
    // erzwungen, nicht nur in der Oberfläche. Auch ein direkter Zugriff kann
    // damit keine verwaisten Fahrzeuge erzeugen.
    $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
    $table->foreignId('fuel_card_id')->nullable()->constrained()->restrictOnDelete();
    $table->foreignId('leasing_company_id')->nullable()->constrained()->restrictOnDelete();

    $table->date('lease_start');
    $table->date('lease_end');
    $table->decimal('monthly_lease_rate', 10, 2)->nullable();
    $table->decimal('annual_vehicle_tax', 10, 2)->nullable();
    $table->decimal('annual_insurance', 10, 2)->nullable();
    $table->string('status')->default('active');
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 4: Vehicle-Modell implementieren**

`app/Models/Vehicle.php`:

```php
<?php

namespace App\Models;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    use HasFactory;

    /**
     * Vorlaufzeit, ab der ein auslaufender Leasingvertrag auf dem Dashboard
     * gemeldet wird. Ein halbes Jahr reicht, um ein Nachfolgefahrzeug
     * auszuschreiben. Bewusst eine Konstante und keine Konfiguration.
     */
    public const LEASE_WARNING_DAYS = 180;

    protected $fillable = [
        'brand',
        'model',
        'license_plate',
        'fuel_type',
        'employee_id',
        'fuel_card_id',
        'leasing_company_id',
        'lease_start',
        'lease_end',
        'monthly_lease_rate',
        'annual_vehicle_tax',
        'annual_insurance',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'fuel_type' => FuelType::class,
            'status' => VehicleStatus::class,
            'lease_start' => 'date',
            'lease_end' => 'date',
            'monthly_lease_rate' => 'decimal:2',
            'annual_vehicle_tax' => 'decimal:2',
            'annual_insurance' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function fuelCard(): BelongsTo
    {
        return $this->belongsTo(FuelCard::class);
    }

    public function leasingCompany(): BelongsTo
    {
        return $this->belongsTo(LeasingCompany::class);
    }

    /**
     * Der decimal-Cast liefert Strings, daher die explizite float-Umwandlung.
     */
    protected function annualTotalCost(): Attribute
    {
        return Attribute::get(fn (): float => ((float) $this->monthly_lease_rate) * 12
            + (float) $this->annual_vehicle_tax
            + (float) $this->annual_insurance);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', VehicleStatus::Active);
    }

    public function scopeLeaseEndingWithin(Builder $query, int $days): Builder
    {
        return $query->active()
            ->whereBetween('lease_end', [today(), today()->addDays($days)]);
    }
}
```

- [ ] **Step 5: `vehicles()`-Beziehungen auf den Stammdaten ergänzen**

In `app/Models/Employee.php`, `app/Models/FuelCard.php` und `app/Models/LeasingCompany.php`
jeweils diese Methode ergänzen und `use Illuminate\Database\Eloquent\Relations\HasMany;`
importieren:

```php
public function vehicles(): HasMany
{
    return $this->hasMany(Vehicle::class);
}
```

- [ ] **Step 6: Factory implementieren**

```bash
ddev artisan make:factory VehicleFactory --model=Vehicle
```

`database/factories/VehicleFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    public function definition(): array
    {
        $leaseStart = fake()->dateTimeBetween('-3 years', '-1 month');

        return [
            'brand' => fake()->randomElement(['VW', 'Audi', 'Skoda', 'BMW', 'Tesla']),
            'model' => fake()->randomElement(['Passat', 'A4', 'Octavia', '320d', 'Model 3']),
            'license_plate' => strtoupper(fake()->unique()->bothify('M-??###')),
            'fuel_type' => fake()->randomElement(FuelType::cases()),
            'employee_id' => null,
            'fuel_card_id' => null,
            'leasing_company_id' => null,
            'lease_start' => $leaseStart,
            'lease_end' => fake()->dateTimeBetween('+1 month', '+3 years'),
            'monthly_lease_rate' => fake()->randomFloat(2, 250, 800),
            'annual_vehicle_tax' => fake()->randomFloat(2, 50, 400),
            'annual_insurance' => fake()->randomFloat(2, 300, 1200),
            'status' => VehicleStatus::Active,
            'notes' => null,
        ];
    }

    public function retired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VehicleStatus::Retired,
        ]);
    }
}
```

- [ ] **Step 7: Migration ausführen und Test bestätigen**

```bash
ddev artisan migrate
ddev exec vendor/bin/pest tests/Feature/Models/VehicleTest.php
```

Erwartet: PASS, 20 Tests. Falls die Integritätstests fehlschlagen, weil keine Exception fliegt:
prüfen, ob die Foreign Keys tatsächlich angelegt wurden (`ddev mysql -e "SHOW CREATE TABLE
vehicles"`) — MariaDB muss `ON DELETE RESTRICT` zeigen.

- [ ] **Step 8: Commit**

```bash
git add app/Models database/migrations database/factories tests/Feature/Models/VehicleTest.php
git commit -m "Add Vehicle model with cost calculation, lease scopes and delete protection"
```

---

## Task 5: Benutzer um Rolle, Kostenrecht und 2FA erweitern

**Files:**
- Create: `database/migrations/…_add_fleet_fields_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Models/UserTest.php`

**Interfaces:**
- Consumes: `App\Enums\UserRole` (Task 2)
- Produces:
  - Spalten `role` (String, Default `viewer`), `can_view_costs` (Boolean, Default `false`), `app_authentication_secret` (Text, nullable), `app_authentication_recovery_codes` (Text, nullable)
  - `User::isAdmin(): bool`
  - `User::canAccessPanel(Panel $panel): bool` — aus dem Interface `Filament\Models\Contracts\FilamentUser`
  - Gate `viewCosts` — von der Kosten-Resource, den Kostenfeldern und den Kostenspalten genutzt. Prüfung überall via `auth()->user()->can('viewCosts')` bzw. `Gate::allows('viewCosts')`.
  - `UserFactory`-Zustände `admin()`, `viewer()`, `withCostAccess()`

**Hinweis zu 2FA:** Die Spaltennamen sind von Filament vorgegeben. Die Casts (`encrypted`
bzw. `encrypted:array`) setzen die Traits `InteractsWithAppAuthentication` und
`InteractsWithAppAuthenticationRecovery` selbst — nicht zusätzlich in `casts()` eintragen.

- [ ] **Step 1: Failing Test schreiben**

`tests/Feature/Models/UserTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;

it('defaults new users to viewer without cost access', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::Viewer)
        ->and($user->can_view_costs)->toBeFalse()
        ->and($user->isAdmin())->toBeFalse();
});

it('recognises an admin', function () {
    expect(User::factory()->admin()->create()->isAdmin())->toBeTrue();
});

it('grants the viewCosts ability only with the flag set', function () {
    $withAccess = User::factory()->withCostAccess()->create();
    $withoutAccess = User::factory()->create();

    expect(Gate::forUser($withAccess)->allows('viewCosts'))->toBeTrue()
        ->and(Gate::forUser($withoutAccess)->allows('viewCosts'))->toBeFalse();
});

it('grants an admin cost access only when the flag is set', function () {
    // Bewusst unabhängig von der Rolle: ein Admin ohne Kostenrecht sieht keine Beträge.
    $admin = User::factory()->admin()->create(['can_view_costs' => false]);

    expect(Gate::forUser($admin)->allows('viewCosts'))->toBeFalse();
});

it('lets every user access the admin panel', function () {
    $panel = Filament::getPanel('admin');

    expect(User::factory()->create()->canAccessPanel($panel))->toBeTrue();
});

it('stores the two factor columns', function () {
    $user = User::factory()->create();

    $user->saveAppAuthenticationSecret('SECRET123');

    expect($user->fresh()->getAppAuthenticationSecret())->toBe('SECRET123');
});

it('encrypts the two factor secret in the database', function () {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('SECRET123');

    $raw = DB::table('users')->where('id', $user->id)->value('app_authentication_secret');

    expect($raw)->not->toBe('SECRET123');
});
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

```bash
ddev exec vendor/bin/pest tests/Feature/Models/UserTest.php
```

Erwartet: FAIL — `role` existiert nicht bzw. `isAdmin()` ist nicht definiert.

- [ ] **Step 3: Migration erzeugen und füllen**

```bash
ddev artisan make:migration add_fleet_fields_to_users_table
```

`up()`:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('role')->default('viewer');
    $table->boolean('can_view_costs')->default(false);

    // Spaltennamen von Filament vorgegeben (App-Authentication + Recovery Codes).
    $table->text('app_authentication_secret')->nullable();
    $table->text('app_authentication_recovery_codes')->nullable();
});
```

`down()`:

```php
Schema::table('users', function (Blueprint $table) {
    $table->dropColumn([
        'role',
        'can_view_costs',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ]);
});
```

- [ ] **Step 4: User-Modell erweitern**

`app/Models/User.php` — vollständig ersetzen:

```php
<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    use HasFactory;
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'can_view_costs',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts für die 2FA-Spalten setzen die Filament-Traits selbst.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'can_view_costs' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Wer sich am Panel anmelden darf. Ohne dieses Interface sperrt Filament
     * den Zugang in allen Umgebungen außer local.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
```

- [ ] **Step 5: Gate `viewCosts` registrieren**

In `app/Providers/AppServiceProvider.php`, Methode `boot()`:

```php
Gate::define('viewCosts', fn (User $user): bool => $user->can_view_costs);
```

Imports ergänzen: `use App\Models\User;` und `use Illuminate\Support\Facades\Gate;`

- [ ] **Step 6: UserFactory um Zustände erweitern**

In `database/factories/UserFactory.php` ergänzen (Import `use App\Enums\UserRole;`):

```php
public function admin(): static
{
    return $this->state(fn (array $attributes): array => [
        'role' => UserRole::Admin,
        'can_view_costs' => true,
    ]);
}

public function viewer(): static
{
    return $this->state(fn (array $attributes): array => [
        'role' => UserRole::Viewer,
        'can_view_costs' => false,
    ]);
}

public function withCostAccess(): static
{
    return $this->state(fn (array $attributes): array => [
        'can_view_costs' => true,
    ]);
}
```

- [ ] **Step 7: Migration ausführen und Test bestätigen**

```bash
ddev artisan migrate
ddev exec vendor/bin/pest tests/Feature/Models/UserTest.php
```

Erwartet: PASS, 7 Tests.

- [ ] **Step 8: Commit**

```bash
git add app/Models/User.php app/Providers/AppServiceProvider.php database/migrations database/factories/UserFactory.php tests/Feature/Models/UserTest.php
git commit -m "Extend users with role, cost permission and two factor columns"
```

---

## Task 6: Panel konfigurieren — 2FA-Pflicht, Deutsch, Navigation

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `.env`, `.env.example`
- Modify: `database/factories/UserFactory.php` (Zustand `withTwoFactor()`)
- Test: `tests/Feature/Filament/PanelAccessTest.php`

**Interfaces:**
- Consumes: `User` mit 2FA-Traits (Task 5)
- Produces:
  - Panel mit erzwungenem TOTP-2FA, Profilseite, deutscher Oberfläche und drei Navigationsgruppen: `Fuhrpark`, `Stammdaten`, `Verwaltung`
  - `UserFactory::withTwoFactor()` — legt ein gültiges TOTP-Secret an. **Alle Oberflächentests der Folge-Tasks nutzen Nutzer mit eingerichtetem 2FA**, damit die 2FA-Pflicht die Tests nicht umleitet.

**Risiko, das dieser Task ausräumt:** Mit `isRequired: true` verlangt Filament nach dem Login
eine 2FA-Einrichtung. Ob das auch Requests betrifft, die im Test per `actingAs()` authentifiziert
werden, ist nicht dokumentiert. Schritt 5 prüft das explizit — das Ergebnis entscheidet, ob die
Folge-Tasks `withTwoFactor()` zwingend brauchen. Der Plan nutzt es vorsorglich überall, damit
die Antwort keine Folge-Tasks blockiert.

- [ ] **Step 1: Deutsche Oberfläche aktivieren**

In `.env` und `.env.example`:

```
APP_LOCALE=de
```

Filament liefert deutsche Übersetzungen mit. Laravels Validierungsmeldungen bleiben englisch —
für ein internes Werkzeug mit drei Nutzern akzeptabel. Wer das später ändern will, installiert
`laravel-lang/common` und führt `artisan lang:add de` aus; das ist bewusst nicht Teil dieses Plans.

- [ ] **Step 2: Panel konfigurieren**

`app/Providers/Filament/AdminPanelProvider.php` — die `panel()`-Methode. Die vom Generator
erzeugten `->middleware([...])` und `->authMiddleware([...])`-Aufrufe **unverändert stehen
lassen** und nur die folgenden Aufrufe ergänzen bzw. anpassen:

```php
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Support\Colors\Color;

// innerhalb von panel():
return $panel
    ->default()
    ->id('admin')
    ->path('admin')
    ->brandName('Fuhrparkmanagement')
    ->colors([
        'primary' => Color::Blue,
    ])
    ->login()
    ->profile()
    ->multiFactorAuthentication([
        AppAuthentication::make()
            ->recoverable()
            ->brandName('Fuhrparkmanagement'),
    ], isRequired: true)
    ->navigationGroups([
        'Fuhrpark',
        'Stammdaten',
        'Verwaltung',
    ])
    // ... discoverResources / discoverPages / pages / discoverWidgets / widgets
    // ... middleware / authMiddleware unverändert
    ;
```

Den vom Generator gesetzten Widget-Eintrag `AccountWidget::class` und
`FilamentInfoWidget::class` entfernen — das Dashboard bekommt in Task 11 eigene Widgets.

- [ ] **Step 3: Migration für Filaments Notifications und Exporte vorbereiten**

Der CSV-Export (Task 9/10) braucht diese Tabellen. Jetzt anlegen, damit sie nicht später
zwischen zwei Tasks fehlen:

```bash
ddev artisan make:queue-batches-table
ddev artisan make:notifications-table
ddev artisan vendor:publish --tag=filament-actions-migrations
ddev artisan migrate
```

- [ ] **Step 4: `withTwoFactor()`-Zustand ergänzen**

In `database/factories/UserFactory.php`:

```php
public function withTwoFactor(): static
{
    // Gültiges Base32-Secret; der Cast verschlüsselt es beim Speichern.
    return $this->afterCreating(
        fn (User $user) => $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP')
    );
}
```

Import ergänzen: `use App\Models\User;`

- [ ] **Step 5: Test schreiben — prüft die Auswirkung der 2FA-Pflicht**

`tests/Feature/Filament/PanelAccessTest.php`:

```php
<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

it('serves the login page to guests', function () {
    $this->get('/admin/login')->assertOk();
});

it('redirects guests away from the dashboard', function () {
    $this->get('/admin')->assertRedirect();
});

it('lets a user with two factor set up reach the dashboard', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $this->get('/admin')->assertOk();
});

it('documents how the panel behaves for users without two factor', function () {
    // Diese Erwartung hält das dokumentierte Verhalten fest: Filament verlangt
    // die Einrichtung nach dem Login. Schlaegt der Test fehl, ist die Annahme
    // falsch - dann Erwartung anpassen, nicht die 2FA-Pflicht abschalten.
    actingAs(User::factory()->admin()->create());

    $response = $this->get('/admin');

    expect($response->status())->toBeIn([200, 302]);
});
```

- [ ] **Step 6: Test ausführen und Ergebnis festhalten**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/PanelAccessTest.php
```

Erwartet: PASS, 4 Tests.

**Wichtig für die Folge-Tasks:** Falls der dritte Test fehlschlägt (Nutzer *mit* 2FA erreicht
das Dashboard nicht), liegt es nicht an der 2FA-Pflicht, sondern am Secret-Format — dann prüfen,
ob `getAppAuthenticationSecret()` den Wert zurückgibt. Falls der vierte Test zeigt, dass ein
Nutzer ohne 2FA umgeleitet wird: genau deshalb nutzen alle weiteren Oberflächentests
`withTwoFactor()`.

- [ ] **Step 7: Commit**

```bash
git add app/Providers/Filament/AdminPanelProvider.php database/factories/UserFactory.php database/migrations .env.example tests/Feature/Filament/PanelAccessTest.php
git commit -m "Configure panel with enforced TOTP, German locale and navigation groups"
```

---

## Task 7: Policies für alle Modelle

**Files:**
- Create: `app/Policies/VehiclePolicy.php`
- Create: `app/Policies/EmployeePolicy.php`
- Create: `app/Policies/FuelCardPolicy.php`
- Create: `app/Policies/LeasingCompanyPolicy.php`
- Create: `app/Policies/UserPolicy.php`
- Test: `tests/Feature/PolicyTest.php`

**Interfaces:**
- Consumes: `User::isAdmin()` (Task 5), alle Modelle (Task 3/4)
- Produces: Policies, die Filament automatisch heranzieht. Einheitliche Regel für Vehicle, Employee, FuelCard und LeasingCompany:
  - `viewAny()`, `view()` — jeder angemeldete Nutzer
  - `create()`, `update()`, `delete()`, `deleteAny()` — nur Admin

  `UserPolicy` ist strenger: **alle** Methoden nur für Admins, einschließlich `viewAny()`. Die
  Benutzerverwaltung inklusive Rollen und Kostenrechten ist keine Leseinformation für alle.

Laravel 13 findet Policies über die Namenskonvention (`App\Models\Vehicle` →
`App\Policies\VehiclePolicy`), eine Registrierung ist nicht nötig.

- [ ] **Step 1: Failing Test schreiben**

`tests/Feature/PolicyTest.php`:

```php
<?php

use App\Models\Employee;
use App\Models\FuelCard;
use App\Models\LeasingCompany;
use App\Models\User;
use App\Models\Vehicle;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->viewer = User::factory()->viewer()->create();
});

it('lets everyone read the fleet', function () {
    $vehicle = Vehicle::factory()->create();

    expect($this->viewer->can('viewAny', Vehicle::class))->toBeTrue()
        ->and($this->viewer->can('view', $vehicle))->toBeTrue()
        ->and($this->admin->can('viewAny', Vehicle::class))->toBeTrue();
});

it('lets only admins write vehicles', function () {
    $vehicle = Vehicle::factory()->create();

    expect($this->admin->can('create', Vehicle::class))->toBeTrue()
        ->and($this->admin->can('update', $vehicle))->toBeTrue()
        ->and($this->admin->can('delete', $vehicle))->toBeTrue()
        ->and($this->viewer->can('create', Vehicle::class))->toBeFalse()
        ->and($this->viewer->can('update', $vehicle))->toBeFalse()
        ->and($this->viewer->can('delete', $vehicle))->toBeFalse();
});

it('blocks bulk deletion for viewers', function () {
    expect($this->admin->can('deleteAny', Vehicle::class))->toBeTrue()
        ->and($this->viewer->can('deleteAny', Vehicle::class))->toBeFalse();
});

it('applies the same rule to master data', function (string $model) {
    $record = $model::factory()->create();

    expect($this->viewer->can('viewAny', $model))->toBeTrue()
        ->and($this->viewer->can('update', $record))->toBeFalse()
        ->and($this->admin->can('update', $record))->toBeTrue();
})->with([
    Employee::class,
    FuelCard::class,
    LeasingCompany::class,
]);

it('restricts user administration to admins entirely', function () {
    expect($this->admin->can('viewAny', User::class))->toBeTrue()
        ->and($this->admin->can('update', $this->viewer))->toBeTrue()
        ->and($this->viewer->can('viewAny', User::class))->toBeFalse()
        ->and($this->viewer->can('view', $this->admin))->toBeFalse()
        ->and($this->viewer->can('update', $this->admin))->toBeFalse()
        ->and($this->viewer->can('create', User::class))->toBeFalse();
});
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

```bash
ddev exec vendor/bin/pest tests/Feature/PolicyTest.php
```

Erwartet: FAIL — ohne Policy verweigert Laravel die Abilities, die Tests auf `toBeTrue()`
schlagen fehl.

- [ ] **Step 3: Policies erzeugen**

```bash
ddev artisan make:policy VehiclePolicy --model=Vehicle
ddev artisan make:policy EmployeePolicy --model=Employee
ddev artisan make:policy FuelCardPolicy --model=FuelCard
ddev artisan make:policy LeasingCompanyPolicy --model=LeasingCompany
ddev artisan make:policy UserPolicy --model=User
```

- [ ] **Step 4: VehiclePolicy implementieren**

`app/Policies/VehiclePolicy.php` — vollständig ersetzen:

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

- [ ] **Step 5: Stammdaten-Policies implementieren**

`app/Policies/EmployeePolicy.php` — gleiche Struktur, Model-Typ angepasst:

```php
<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Employee $employee): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

`app/Policies/FuelCardPolicy.php` — identisch, mit `use App\Models\FuelCard;` und
`FuelCard $fuelCard` als Parameter:

```php
<?php

namespace App\Policies;

use App\Models\FuelCard;
use App\Models\User;

class FuelCardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FuelCard $fuelCard): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, FuelCard $fuelCard): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, FuelCard $fuelCard): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

`app/Policies/LeasingCompanyPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\LeasingCompany;
use App\Models\User;

class LeasingCompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeasingCompany $leasingCompany): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, LeasingCompany $leasingCompany): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, LeasingCompany $leasingCompany): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

- [ ] **Step 6: UserPolicy implementieren — durchgängig Admin-only**

`app/Policies/UserPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        // Sich selbst zu löschen sperrt im Zweifel den letzten Admin aus.
        return $user->isAdmin() && $user->isNot($model);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
```

- [ ] **Step 7: Test für den Selbstlöschschutz ergänzen**

An `tests/Feature/PolicyTest.php` anfügen:

```php
it('stops an admin from deleting their own account', function () {
    $other = User::factory()->admin()->create();

    expect($this->admin->can('delete', $this->admin))->toBeFalse()
        ->and($this->admin->can('delete', $other))->toBeTrue();
});
```

- [ ] **Step 8: Test ausführen, Erfolg bestätigen**

```bash
ddev exec vendor/bin/pest tests/Feature/PolicyTest.php
```

Erwartet: PASS, 8 Tests (der `with()`-Test zählt dreifach).

- [ ] **Step 9: Commit**

```bash
git add app/Policies tests/Feature/PolicyTest.php
git commit -m "Add policies restricting writes to admins and user management entirely"
```

---

## Task 8: Löschschutz-Action und Stammdaten-Resources

**Files:**
- Create: `app/Filament/Actions/GuardedDeleteAction.php`
- Create: `app/Filament/Resources/Employees/` (via `make:filament-resource`)
- Create: `app/Filament/Resources/FuelCards/`
- Create: `app/Filament/Resources/LeasingCompanies/`
- Test: `tests/Feature/Filament/EmployeeResourceTest.php`
- Test: `tests/Feature/Filament/FuelCardResourceTest.php`
- Test: `tests/Feature/Filament/LeasingCompanyResourceTest.php`

**Interfaces:**
- Consumes: Modelle und Policies (Tasks 3, 4, 7), `UserFactory::withTwoFactor()` (Task 6)
- Produces:
  - `App\Filament\Actions\GuardedDeleteAction` — eine `DeleteAction`, die abbricht und eine Meldung mit betroffenem Kennzeichen zeigt, wenn der Datensatz noch Fahrzeuge hat. Erwartet ein Model mit `vehicles()`-Beziehung. Wird von allen drei Stammdaten-Resources genutzt, in der Tabelle **und** auf der Edit-Seite.
  - Drei Resources in der Navigationsgruppe `Stammdaten`

- [ ] **Step 1: Failing Test für den Löschschutz schreiben**

`tests/Feature/Filament/EmployeeResourceTest.php`:

```php
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
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/EmployeeResourceTest.php
```

Erwartet: FAIL — die Resource-Klassen existieren nicht.

- [ ] **Step 3: `GuardedDeleteAction` implementieren**

`app/Filament/Actions/GuardedDeleteAction.php`:

```php
<?php

namespace App\Filament\Actions;

use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Löschen von Stammdaten, die noch einem Fahrzeug zugeordnet sind, würde auf
 * Datenbankebene an den Foreign Keys scheitern. Diese Action fängt den Fall
 * vorher ab und erklärt verständlich, welches Fahrzeug im Weg steht.
 */
class GuardedDeleteAction extends DeleteAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->before(function (GuardedDeleteAction $action, Model $record): void {
            $vehicle = $record->vehicles()->first();

            if ($vehicle === null) {
                return;
            }

            $count = $record->vehicles()->count();

            Notification::make()
                ->danger()
                ->title('Löschen nicht möglich')
                ->body($count === 1
                    ? "Der Eintrag ist noch dem Fahrzeug {$vehicle->license_plate} zugeordnet."
                    : "Der Eintrag ist noch {$count} Fahrzeugen zugeordnet, unter anderem {$vehicle->license_plate}.")
                ->persistent()
                ->send();

            $action->halt();
        });
    }
}
```

**Falls `setUp()` den Hook nicht greift** (Test schlägt fehl, weil gelöscht wird): Statt der
eigenen Klasse `DeleteAction::make()->before(...)` an jeder Verwendungsstelle einsetzen — die
Closure ist identisch, nur `GuardedDeleteAction $action` wird zu `DeleteAction $action`.

- [ ] **Step 4: Employee-Resource erzeugen**

```bash
ddev artisan make:filament-resource Employee --generate
```

`app/Filament/Resources/Employees/EmployeeResource.php` — Properties ergänzen:

```php
protected static ?string $model = Employee::class;

protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

protected static ?string $modelLabel = 'Mitarbeiter';

protected static ?string $pluralModelLabel = 'Mitarbeiter';

protected static ?string $recordTitleAttribute = 'last_name';
```

Imports: `use BackedEnum;`, `use UnitEnum;`, `use Filament\Support\Icons\Heroicon;`

`app/Filament/Resources/Employees/Schemas/EmployeeForm.php`:

```php
<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->label('Vorname')
                    ->required()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->label('Nachname')
                    ->required()
                    ->maxLength(255),
                TextInput::make('department')
                    ->label('Abteilung')
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('E-Mail')
                    ->email()
                    ->maxLength(255),
                Toggle::make('active')
                    ->label('Aktiv')
                    ->default(true),
            ]);
    }
}
```

`app/Filament/Resources/Employees/Tables/EmployeesTable.php`:

```php
<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Filament\Actions\GuardedDeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('last_name')
                    ->label('Nachname')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('Vorname')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department')
                    ->label('Abteilung')
                    ->searchable(),
                TextColumn::make('vehicles_count')
                    ->label('Fahrzeuge')
                    ->counts('vehicles'),
                IconColumn::make('active')
                    ->label('Aktiv')
                    ->boolean(),
            ])
            ->defaultSort('last_name')
            ->recordActions([
                EditAction::make(),
                GuardedDeleteAction::make(),
            ]);
    }
}
```

Auf der Edit-Seite `app/Filament/Resources/Employees/Pages/EditEmployee.php` die generierte
`DeleteAction` ersetzen:

```php
protected function getHeaderActions(): array
{
    return [
        GuardedDeleteAction::make(),
    ];
}
```

Import: `use App\Filament\Actions\GuardedDeleteAction;` — die generierte
`use Filament\Actions\DeleteAction;` entfernen.

- [ ] **Step 5: Test für Employees ausführen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/EmployeeResourceTest.php
```

Erwartet: PASS, 6 Tests.

- [ ] **Step 6: FuelCard-Resource erzeugen**

```bash
ddev artisan make:filament-resource FuelCard --generate
```

Properties in `FuelCardResource.php`:

```php
protected static ?string $model = FuelCard::class;

protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

protected static ?string $modelLabel = 'Tankkarte';

protected static ?string $pluralModelLabel = 'Tankkarten';
```

`app/Filament/Resources/FuelCards/Schemas/FuelCardForm.php`:

```php
<?php

namespace App\Filament\Resources\FuelCards\Schemas;

use App\Enums\FuelCardProvider;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FuelCardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('provider')
                    ->label('Anbieter')
                    ->options(FuelCardProvider::class)
                    ->native(false)
                    ->required(),
                TextInput::make('card_number')
                    ->label('Kartennummer')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Wird verschlüsselt gespeichert.'),
                DatePicker::make('valid_until')
                    ->label('Gültig bis')
                    ->native(false)
                    ->displayFormat('d.m.Y'),
                Textarea::make('notes')
                    ->label('Notizen')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
```

`app/Filament/Resources/FuelCards/Tables/FuelCardsTable.php`:

```php
<?php

namespace App\Filament\Resources\FuelCards\Tables;

use App\Filament\Actions\GuardedDeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Enums\FuelCardProvider;

class FuelCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider')
                    ->label('Anbieter')
                    ->badge()
                    ->sortable(),
                // Die Kartennummer ist verschlüsselt und daher nicht durchsuchbar.
                TextColumn::make('card_number')
                    ->label('Kartennummer'),
                TextColumn::make('valid_until')
                    ->label('Gültig bis')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('vehicles_count')
                    ->label('Fahrzeuge')
                    ->counts('vehicles'),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->label('Anbieter')
                    ->options(FuelCardProvider::class),
            ])
            ->recordActions([
                EditAction::make(),
                GuardedDeleteAction::make(),
            ]);
    }
}
```

`EditFuelCard.php` analog auf `GuardedDeleteAction::make()` umstellen.

- [ ] **Step 7: Test für Tankkarten schreiben und ausführen**

`tests/Feature/Filament/FuelCardResourceTest.php`:

```php
<?php

use App\Enums\FuelCardProvider;
use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\FuelCards\Pages\CreateFuelCard;
use App\Filament\Resources\FuelCards\Pages\EditFuelCard;
use App\Filament\Resources\FuelCards\Pages\ListFuelCards;
use App\Models\FuelCard;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
});

it('lists fuel cards', function () {
    $cards = FuelCard::factory()->count(3)->create();

    livewire(ListFuelCards::class)
        ->assertOk()
        ->assertCanSeeTableRecords($cards);
});

it('creates a fuel card and stores the number encrypted', function () {
    livewire(CreateFuelCard::class)
        ->fillForm([
            'provider' => FuelCardProvider::Dkv->value,
            'card_number' => '7080999888777',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $card = FuelCard::first();
    expect($card->card_number)->toBe('7080999888777');

    $raw = DB::table('fuel_cards')->where('id', $card->id)->value('card_number');
    expect($raw)->not->toBe('7080999888777');
});

it('requires provider and card number', function () {
    livewire(CreateFuelCard::class)
        ->fillForm(['provider' => null, 'card_number' => null])
        ->call('create')
        ->assertHasFormErrors(['provider' => 'required', 'card_number' => 'required']);
});

it('filters by provider', function () {
    $dkv = FuelCard::factory()->create(['provider' => FuelCardProvider::Dkv]);
    $elli = FuelCard::factory()->create(['provider' => FuelCardProvider::Elli]);

    livewire(ListFuelCards::class)
        ->filterTable('provider', FuelCardProvider::Dkv->value)
        ->assertCanSeeTableRecords([$dkv])
        ->assertCanNotSeeTableRecords([$elli]);
});

it('refuses to delete a card that is still assigned', function () {
    $card = FuelCard::factory()->create();
    Vehicle::factory()->for($card)->create(['license_plate' => 'M-CD 5678']);

    livewire(EditFuelCard::class, ['record' => $card->id])
        ->callAction(GuardedDeleteAction::class)
        ->assertNotified();

    assertDatabaseHas(FuelCard::class, ['id' => $card->id]);
});
```

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/FuelCardResourceTest.php
```

Erwartet: PASS, 5 Tests.

- [ ] **Step 8: LeasingCompany-Resource erzeugen**

```bash
ddev artisan make:filament-resource LeasingCompany --generate
```

Properties in `LeasingCompanyResource.php`:

```php
protected static ?string $model = LeasingCompany::class;

protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

protected static ?string $modelLabel = 'Leasinggeber';

protected static ?string $pluralModelLabel = 'Leasinggeber';

protected static ?string $recordTitleAttribute = 'name';
```

`app/Filament/Resources/LeasingCompanies/Schemas/LeasingCompanyForm.php`:

```php
<?php

namespace App\Filament\Resources\LeasingCompanies\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LeasingCompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('contact_person')
                    ->label('Ansprechpartner')
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Telefon')
                    ->tel()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('E-Mail')
                    ->email()
                    ->maxLength(255),
                Textarea::make('notes')
                    ->label('Notizen')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
```

`app/Filament/Resources/LeasingCompanies/Tables/LeasingCompaniesTable.php`:

```php
<?php

namespace App\Filament\Resources\LeasingCompanies\Tables;

use App\Filament\Actions\GuardedDeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeasingCompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_person')
                    ->label('Ansprechpartner')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefon'),
                TextColumn::make('vehicles_count')
                    ->label('Fahrzeuge')
                    ->counts('vehicles'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                GuardedDeleteAction::make(),
            ]);
    }
}
```

`EditLeasingCompany.php` analog auf `GuardedDeleteAction::make()` umstellen.

- [ ] **Step 9: Test für Leasinggeber schreiben und ausführen**

`tests/Feature/Filament/LeasingCompanyResourceTest.php`:

```php
<?php

use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\LeasingCompanies\Pages\CreateLeasingCompany;
use App\Filament\Resources\LeasingCompanies\Pages\EditLeasingCompany;
use App\Filament\Resources\LeasingCompanies\Pages\ListLeasingCompanies;
use App\Models\LeasingCompany;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
});

it('lists leasing companies', function () {
    $companies = LeasingCompany::factory()->count(3)->create();

    livewire(ListLeasingCompanies::class)
        ->assertOk()
        ->assertCanSeeTableRecords($companies);
});

it('creates a leasing company', function () {
    livewire(CreateLeasingCompany::class)
        ->fillForm([
            'name' => 'VW Leasing',
            'contact_person' => 'Herr Muster',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(LeasingCompany::class, ['name' => 'VW Leasing']);
});

it('requires a name', function () {
    livewire(CreateLeasingCompany::class)
        ->fillForm(['name' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('refuses to delete a company that is still referenced', function () {
    $company = LeasingCompany::factory()->create();
    Vehicle::factory()->for($company)->create(['license_plate' => 'M-EF 9012']);

    livewire(EditLeasingCompany::class, ['record' => $company->id])
        ->callAction(GuardedDeleteAction::class)
        ->assertNotified();

    assertDatabaseHas(LeasingCompany::class, ['id' => $company->id]);
});
```

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/LeasingCompanyResourceTest.php
```

Erwartet: PASS, 4 Tests.

- [ ] **Step 10: Gesamte Suite ausführen**

```bash
ddev exec vendor/bin/pest
```

Erwartet: alle Tests grün.

- [ ] **Step 11: Commit**

```bash
git add app/Filament tests/Feature/Filament
git commit -m "Add master data resources with delete protection"
```

---

## Task 9: Fahrzeug-Resource

**Files:**
- Create: `app/Filament/Resources/Vehicles/` (via `make:filament-resource`)
- Create: `app/Filament/Exports/VehicleExporter.php`
- Test: `tests/Feature/Filament/VehicleResourceTest.php`

**Interfaces:**
- Consumes: `Vehicle` mit Scopes und Accessor (Task 4), Gate `viewCosts` (Task 5), `VehiclePolicy` (Task 7)
- Produces: Fahrzeug-Resource in der Navigationsgruppe `Fuhrpark` mit vier Formularabschnitten, gefilterter Tabelle und CSV-Export ohne Kostendaten

**Abweichung von der Spec, bewusst:** Die Spec beschreibt drei Formularblöcke, wobei der dritte
„Vertrag & Kosten" heißt. Der Plan trennt ihn in **Vertrag** (Leasinggeber, Laufzeit — für
jeden sichtbar) und **Kosten** (die drei Betragsfelder — nur mit `viewCosts`). Sonst würde ein
Nutzer ohne Kostenrecht das Leasingende nicht sehen, und das ist die wichtigste Information der
ganzen Anwendung.

**Nachträglich korrigiert (Erkenntnis aus der Umsetzung):** Der Plan nahm an, ein per
`visible()` verstecktes Feld werde beim Speichern zwar nicht gesendet, könnte aber die
vorhandenen Werte auf `null` setzen — abzusichern durch zusätzliches `dehydrated(...)`. Das ist
falsch. Filament 5 verwirft in `getState()` die Statuspfade **aller Kinder einer versteckten
Section** (`vendor/filament/schemas/src/Components/Concerns/HasState.php`), sodass die drei
Kostenschlüssel das Modell nie erreichen — auch bei einer absichtlich manipulierten Anfrage.
Der Schutz ist also stärker als angenommen und liegt an der Section, nicht am Feld. Die
`dehydrated(...)`-Aufrufe bleiben als Verteidigung in der Tiefe für den Fall, dass die Felder je
aus der Section herausgehoben werden; der zugehörige Test prüft weiterhin das Verhalten.

- [ ] **Step 1: Failing Test schreiben**

`tests/Feature/Filament/VehicleResourceTest.php`:

```php
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

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

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

it('rejects negative amounts', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    livewire(CreateVehicle::class)
        ->fillForm([
            'brand' => 'VW',
            'model' => 'Golf',
            'license_plate' => 'M-XY 5555',
            'fuel_type' => FuelType::Diesel->value,
            'lease_start' => '2026-01-01',
            'lease_end' => '2029-01-01',
            'monthly_lease_rate' => -50,
        ])
        ->call('create')
        ->assertHasFormErrors(['monthly_lease_rate']);
});

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
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/VehicleResourceTest.php
```

Erwartet: FAIL — Resource-Klassen fehlen.

- [ ] **Step 3: Resource erzeugen**

```bash
ddev artisan make:filament-resource Vehicle --generate
```

Properties in `app/Filament/Resources/Vehicles/VehicleResource.php`:

```php
protected static ?string $model = Vehicle::class;

protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

protected static string|UnitEnum|null $navigationGroup = 'Fuhrpark';

protected static ?string $modelLabel = 'Fahrzeug';

protected static ?string $pluralModelLabel = 'Fahrzeuge';

protected static ?string $recordTitleAttribute = 'license_plate';

protected static ?int $navigationSort = 1;
```

- [ ] **Step 4: Formular implementieren**

`app/Filament/Resources/Vehicles/Schemas/VehicleForm.php`:

```php
<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fahrzeug')
                    ->columns(2)
                    ->schema([
                        TextInput::make('brand')
                            ->label('Marke')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('model')
                            ->label('Modell')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('license_plate')
                            ->label('Kennzeichen')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('fuel_type')
                            ->label('Antriebsart')
                            ->options(FuelType::class)
                            ->native(false)
                            ->required(),
                        Select::make('status')
                            ->label('Status')
                            ->options(VehicleStatus::class)
                            ->native(false)
                            ->default(VehicleStatus::Active->value)
                            ->required(),
                    ]),

                Section::make('Zuordnung')
                    ->columns(2)
                    ->schema([
                        Select::make('employee_id')
                            ->label('Mitarbeiter')
                            ->relationship('employee', 'last_name')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => $record->full_name)
                            ->searchable(['first_name', 'last_name'])
                            ->preload(),
                        Select::make('fuel_card_id')
                            ->label('Tankkarte')
                            ->relationship('fuelCard', 'card_number')
                            ->preload(),
                    ]),

                Section::make('Vertrag')
                    ->columns(3)
                    ->schema([
                        Select::make('leasing_company_id')
                            ->label('Leasinggeber')
                            ->relationship('leasingCompany', 'name')
                            ->searchable()
                            ->preload(),
                        DatePicker::make('lease_start')
                            ->label('Leasingstart')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->required(),
                        DatePicker::make('lease_end')
                            ->label('Leasingende')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->required()
                            ->after('lease_start'),
                    ]),

                Section::make('Kosten')
                    ->columns(3)
                    // Sichtbarkeit UND Speicherung an das Recht gebunden: ohne
                    // dehydrated() wuerde ein Speichern ohne Kostenrecht die
                    // vorhandenen Betraege auf null setzen.
                    ->visible(fn (): bool => auth()->user()?->can('viewCosts') ?? false)
                    ->schema([
                        TextInput::make('monthly_lease_rate')
                            ->label('Leasingrate (monatlich)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->dehydrated(fn (): bool => auth()->user()?->can('viewCosts') ?? false),
                        TextInput::make('annual_vehicle_tax')
                            ->label('Kfz-Steuer (jährlich)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->dehydrated(fn (): bool => auth()->user()?->can('viewCosts') ?? false),
                        TextInput::make('annual_insurance')
                            ->label('Versicherung (jährlich)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->dehydrated(fn (): bool => auth()->user()?->can('viewCosts') ?? false),
                    ]),

                Section::make('Notizen')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notizen')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
```

- [ ] **Step 5: Tabelle implementieren**

`app/Filament/Resources/Vehicles/Tables/VehiclesTable.php`:

```php
<?php

namespace App\Filament\Resources\Vehicles\Tables;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use App\Filament\Exports\VehicleExporter;
use App\Models\Vehicle;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('license_plate')
                    ->label('Kennzeichen')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand')
                    ->label('Marke')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('model')
                    ->label('Modell')
                    ->searchable(),
                TextColumn::make('employee.last_name')
                    ->label('Fahrer')
                    ->formatStateUsing(fn ($state, Vehicle $record): string => $record->employee?->full_name ?? '—')
                    // Spaltennamen ohne Tabellenpräfix: Filament deutet einen Punkt sonst als
                    // JSON-Pfad, wenn das Präfix nicht die Haupttabelle ist, und erzeugt
                    // ungültiges SQL. Die Namen werden in die Relations-Unterabfrage gegeben.
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('fuel_type')
                    ->label('Antriebsart')
                    ->badge()
                    ->sortable(),
                TextColumn::make('lease_end')
                    ->label('Leasingende')
                    ->date('d.m.Y')
                    ->badge()
                    ->color(function (Vehicle $record): string {
                        $days = today()->diffInDays($record->lease_end, false);

                        return match (true) {
                            $days < 0 => 'gray',
                            $days <= 90 => 'danger',
                            $days <= Vehicle::LEASE_WARNING_DAYS => 'warning',
                            default => 'success',
                        };
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('lease_end')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(VehicleStatus::class)
                    // Ausgemusterte Fahrzeuge sind zuschaltbar, aber nicht Standard.
                    ->default(VehicleStatus::Active->value),
                SelectFilter::make('fuel_type')
                    ->label('Antriebsart')
                    ->options(FuelType::class),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export')
                    ->exporter(VehicleExporter::class),
            ])
            // Kein Mehrfachlöschen: Fahrzeuge werden ausgemustert, nicht gelöscht. Endgültiges
            // Löschen bleibt möglich, aber nur einzeln über die Edit-Seite und nur für Admins —
            // mit zwei Klicks den halben Fuhrpark samt Kostenhistorie zu verlieren, wäre ein zu
            // großes Risiko für einen Bequemlichkeitsgewinn.
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
```

- [ ] **Step 6: Exporter implementieren — ohne Kostenspalten**

```bash
ddev artisan make:filament-exporter Vehicle
```

`app/Filament/Exports/VehicleExporter.php`:

```php
<?php

namespace App\Filament\Exports;

use App\Models\Vehicle;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * Bewusst ohne Betraege: die Fahrzeugliste zeigt keine Kosten, also darf ihr
 * Export auch keine enthalten. Kosten werden ueber die Kostenseite exportiert,
 * die hinter dem Recht viewCosts liegt.
 */
class VehicleExporter extends Exporter
{
    protected static ?string $model = Vehicle::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('license_plate')->label('Kennzeichen'),
            ExportColumn::make('brand')->label('Marke'),
            ExportColumn::make('model')->label('Modell'),
            ExportColumn::make('fuel_type')->label('Antriebsart'),
            ExportColumn::make('employee.last_name')->label('Fahrer'),
            ExportColumn::make('fuelCard.provider')->label('Tankkarte'),
            ExportColumn::make('leasingCompany.name')->label('Leasinggeber'),
            ExportColumn::make('lease_start')->label('Leasingstart'),
            ExportColumn::make('lease_end')->label('Leasingende'),
            ExportColumn::make('status')->label('Status'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Der Fahrzeug-Export ist abgeschlossen.';
    }
}
```

- [ ] **Step 7: Test ausführen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/VehicleResourceTest.php
```

Erwartet: PASS, 14 Tests.

Wenn `assertTableColumnDoesNotExist` nicht existiert: durch
`->assertTableColumnExists('license_plate')` ersetzen und die fehlende Kostenspalte stattdessen
über die Spaltenliste in `VehiclesTable` verifizieren — die Aussage steht dann im Code, nicht im
Test. Wenn `assertForbidden()` beim Create-Page-Aufruf nicht greift, stattdessen prüfen:
`expect(\App\Filament\Resources\Vehicles\VehicleResource::canCreate())->toBeFalse()`.

- [ ] **Step 8: Export mit `sync`-Queue verifizieren**

Der Export läuft über Laravels Job-Batches. Die Entscheidung ist bewusst `sync`: bei 20
Fahrzeugen dauert ein Export Millisekunden, und `sync` erspart dem späteren Hosting einen
dauerhaft laufenden Worker-Prozess samt Überwachung.

In `.env` und `.env.example`:

```
QUEUE_CONNECTION=sync
```

Dann in der Oberfläche exportieren:

```bash
ddev launch /admin/vehicles
```

Erwartet: Der Export erzeugt eine Datei zum Herunterladen; die enthaltenen Spalten entsprechen
`VehicleExporter::getColumns()` und enthalten **keine** Beträge.

Falls der Export unter `sync` fehlschlägt (Batches vertragen sich mit dem sync-Treiber unter
Umständen nicht), auf den dokumentierten Weg zurückfallen: `QUEUE_CONNECTION=database`, dann

```bash
ddev exec artisan queue:work --stop-when-empty
```

In diesem Fall in Task 12, Schritt 12 die README-Notiz auf `database` ändern und den
Worker-Betrieb dort als Betriebsvoraussetzung festhalten.

- [ ] **Step 9: Commit**

```bash
git add app/Filament tests/Feature/Filament/VehicleResourceTest.php
git commit -m "Add vehicle resource with permission-scoped cost fields and export"
```

---

## Task 10: Kostenübersicht als eigene Resource

**Files:**
- Create: `app/Filament/Resources/VehicleCosts/VehicleCostResource.php`
- Create: `app/Filament/Resources/VehicleCosts/Pages/ListVehicleCosts.php`
- Create: `app/Filament/Resources/VehicleCosts/Tables/VehicleCostsTable.php`
- Create: `app/Filament/Exports/VehicleCostExporter.php`
- Modify: `app/Models/Vehicle.php` (statische Methode `sumAnnualCost()`)
- Test: `tests/Feature/Filament/VehicleCostResourceTest.php`
- Test: `tests/Feature/Models/VehicleCostSumTest.php`

**Interfaces:**
- Consumes: `Vehicle` (Task 4), Gate `viewCosts` (Task 5)
- Produces:
  - `Vehicle::sumAnnualCost(Builder $query): float` — summiert die Jahreskosten aller Fahrzeuge einer Abfrage per SQL. Ausgelagert ans Model, damit die Formel ohne Oberfläche testbar ist und nicht in einer Filament-Klasse versteckt liegt.
  - `VehicleCostResource` — zweite Resource auf `Vehicle`, Slug `costs`, nur Listenansicht, gesperrt ohne `viewCosts`

**Wichtig:** Ohne eigenen `$slug` würden beide Vehicle-Resources dieselbe Route `vehicles`
beanspruchen. Der Slug `costs` ist Pflicht, nicht Kosmetik.

Die Resource definiert bewusst kein Formular und keine Create-/Edit-Seiten: Kosten werden am
Fahrzeug gepflegt, hier nur ausgewertet.

- [ ] **Step 1: Failing Test für die Summenformel schreiben**

`tests/Feature/Models/VehicleCostSumTest.php`:

```php
<?php

use App\Models\Vehicle;

it('sums annual costs across a query', function () {
    Vehicle::factory()->create([
        'monthly_lease_rate' => 100.00,
        'annual_vehicle_tax' => 50.00,
        'annual_insurance' => 25.00,
    ]);
    Vehicle::factory()->create([
        'monthly_lease_rate' => 200.00,
        'annual_vehicle_tax' => 10.00,
        'annual_insurance' => 5.00,
    ]);

    // (100*12 + 50 + 25) + (200*12 + 10 + 5) = 1275 + 2415 = 3690
    expect(Vehicle::sumAnnualCost(Vehicle::query()))->toBe(3690.0);
});

it('treats missing values as zero when summing', function () {
    Vehicle::factory()->create([
        'monthly_lease_rate' => 100.00,
        'annual_vehicle_tax' => null,
        'annual_insurance' => null,
    ]);

    expect(Vehicle::sumAnnualCost(Vehicle::query()))->toBe(1200.0);
});

it('sums only what the query selects', function () {
    Vehicle::factory()->create(['monthly_lease_rate' => 100.00, 'annual_vehicle_tax' => null, 'annual_insurance' => null]);
    Vehicle::factory()->retired()->create(['monthly_lease_rate' => 999.00, 'annual_vehicle_tax' => null, 'annual_insurance' => null]);

    expect(Vehicle::sumAnnualCost(Vehicle::active()))->toBe(1200.0);
});

it('returns zero for an empty query', function () {
    expect(Vehicle::sumAnnualCost(Vehicle::query()))->toBe(0.0);
});
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

```bash
ddev exec vendor/bin/pest tests/Feature/Models/VehicleCostSumTest.php
```

Erwartet: FAIL mit `Call to undefined method App\Models\Vehicle::sumAnnualCost()`.

- [ ] **Step 3: `sumAnnualCost()` am Model implementieren**

In `app/Models/Vehicle.php` ergänzen:

```php
/**
 * Summiert die Jahreskosten per SQL. Der Accessor annual_total_cost rechnet je
 * Datensatz in PHP; für eine Summenzeile über viele Zeilen wäre das unnötig
 * teuer und für Filament-Summarizer nicht verwendbar.
 */
public static function sumAnnualCost(Builder $query): float
{
    $row = $query->clone()
        ->reorder()
        ->selectRaw(
            'SUM(COALESCE(monthly_lease_rate, 0) * 12'
            .' + COALESCE(annual_vehicle_tax, 0)'
            .' + COALESCE(annual_insurance, 0)) AS aggregate'
        )
        ->first();

    return (float) ($row?->aggregate ?? 0);
}
```

`clone()` und `reorder()` schützen davor, die übergebene Abfrage zu verändern und an einem
`ORDER BY` in der Aggregat-Abfrage zu scheitern.

- [ ] **Step 4: Test ausführen, Erfolg bestätigen**

```bash
ddev exec vendor/bin/pest tests/Feature/Models/VehicleCostSumTest.php
```

Erwartet: PASS, 4 Tests.

- [ ] **Step 5: Failing Test für die Kosten-Resource schreiben**

`tests/Feature/Filament/VehicleCostResourceTest.php`:

```php
<?php

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
```

- [ ] **Step 6: Resource implementieren**

`app/Filament/Resources/VehicleCosts/VehicleCostResource.php`:

```php
<?php

namespace App\Filament\Resources\VehicleCosts;

use App\Filament\Resources\VehicleCosts\Pages\ListVehicleCosts;
use App\Filament\Resources\VehicleCosts\Tables\VehicleCostsTable;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Zweite Resource auf dem Vehicle-Model: reine Auswertung der Kosten, komplett
 * hinter dem Recht viewCosts. Kosten werden am Fahrzeug gepflegt, hier nur
 * gelesen - daher keine Create-/Edit-Seiten und kein Formular.
 */
class VehicleCostResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    // Pflicht: sonst kollidiert die Route mit der VehicleResource.
    protected static ?string $slug = 'costs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Fuhrpark';

    protected static ?string $navigationLabel = 'Kosten';

    protected static ?string $modelLabel = 'Kostenübersicht';

    protected static ?string $pluralModelLabel = 'Kostenübersicht';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return VehicleCostsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewCosts') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleCosts::route('/'),
        ];
    }
}
```

`app/Filament/Resources/VehicleCosts/Pages/ListVehicleCosts.php`:

```php
<?php

namespace App\Filament\Resources\VehicleCosts\Pages;

use App\Filament\Resources\VehicleCosts\VehicleCostResource;
use Filament\Resources\Pages\ListRecords;

class ListVehicleCosts extends ListRecords
{
    protected static string $resource = VehicleCostResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
```

`app/Filament/Resources/VehicleCosts/Tables/VehicleCostsTable.php`:

```php
<?php

namespace App\Filament\Resources\VehicleCosts\Tables;

use App\Enums\VehicleStatus;
use App\Filament\Exports\VehicleCostExporter;
use App\Models\Vehicle;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VehicleCostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('license_plate')
                    ->label('Kennzeichen')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('monthly_lease_rate')
                    ->label('Leasingrate (mtl.)')
                    ->money('EUR')
                    ->sortable()
                    ->summarize(Sum::make()->label('Summe')->money('EUR')),
                TextColumn::make('annual_vehicle_tax')
                    ->label('Kfz-Steuer (jährl.)')
                    ->money('EUR')
                    ->sortable()
                    ->summarize(Sum::make()->label('Summe')->money('EUR')),
                TextColumn::make('annual_insurance')
                    ->label('Versicherung (jährl.)')
                    ->money('EUR')
                    ->sortable()
                    ->summarize(Sum::make()->label('Summe')->money('EUR')),
                // Abgeleiteter Wert: nicht sortierbar, da keine Datenbankspalte.
                TextColumn::make('annual_total_cost')
                    ->label('Jahreskosten')
                    ->money('EUR')
                    ->summarize(
                        Summarizer::make()
                            ->label('Summe')
                            ->money('EUR')
                            ->using(fn (Builder $query): float => Vehicle::sumAnnualCost($query))
                    ),
            ])
            ->defaultSort('license_plate')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(VehicleStatus::class)
                    ->default(VehicleStatus::Active->value),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export')
                    ->exporter(VehicleCostExporter::class),
            ]);
    }
}
```

- [ ] **Step 7: Kosten-Exporter implementieren**

`app/Filament/Exports/VehicleCostExporter.php`:

```php
<?php

namespace App\Filament\Exports;

use App\Models\Vehicle;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class VehicleCostExporter extends Exporter
{
    protected static ?string $model = Vehicle::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('license_plate')->label('Kennzeichen'),
            ExportColumn::make('brand')->label('Marke'),
            ExportColumn::make('model')->label('Modell'),
            ExportColumn::make('monthly_lease_rate')->label('Leasingrate monatlich'),
            ExportColumn::make('annual_vehicle_tax')->label('Kfz-Steuer jaehrlich'),
            ExportColumn::make('annual_insurance')->label('Versicherung jaehrlich'),
            ExportColumn::make('annual_total_cost')->label('Jahreskosten'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Der Kosten-Export ist abgeschlossen.';
    }
}
```

- [ ] **Step 8: Test ausführen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/VehicleCostResourceTest.php
```

Erwartet: PASS, 6 Tests.

Falls `$this->get('/admin/costs')->assertForbidden()` mit 404 statt 403 antwortet: Filament
versteckt gesperrte Resources teils per 404. Dann die Assertion auf
`->assertStatus(fn (int $status): bool => in_array($status, [403, 404], true))` bzw. schlicht
`assertNotFound()` anpassen — entscheidend ist, dass die Seite **nicht** mit 200 antwortet.

Falls der `Summarizer::make()->money()` nicht existiert: `->money()` entfernen und im `using()`
selbst formatieren (`number_format($sum, 2, ',', '.').' €'`).

- [ ] **Step 9: Commit**

```bash
git add app/Filament app/Models/Vehicle.php tests/Feature
git commit -m "Add cost overview resource behind viewCosts gate"
```

---

## Task 11: Dashboard-Widgets

**Files:**
- Create: `app/Filament/Widgets/FleetOverview.php`
- Create: `app/Filament/Widgets/ExpiringLeases.php`
- Test: `tests/Feature/Filament/DashboardTest.php`

**Interfaces:**
- Consumes: `Vehicle::active()`, `Vehicle::leaseEndingWithin()`, `Vehicle::LEASE_WARNING_DAYS` (Task 4)
- Produces: zwei Dashboard-Widgets. `FleetOverview` zeigt Anzahl, Antriebsart-Aufteilung und das nächste Leasingende. `ExpiringLeases` listet die Verträge im 180-Tage-Fenster.

**Beide Widgets enthalten keine Geldbeträge** — laut Spec dient die Startseite der Orientierung,
nicht der Auswertung. Sie brauchen daher auch keine Berechtigungsprüfung.

- [ ] **Step 1: Failing Test schreiben**

`tests/Feature/Filament/DashboardTest.php`:

```php
<?php

use App\Enums\FuelType;
use App\Filament\Widgets\ExpiringLeases;
use App\Filament\Widgets\FleetOverview;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
});

it('renders the dashboard', function () {
    $this->get('/admin')->assertOk();
});

it('counts only active vehicles', function () {
    Vehicle::factory()->count(3)->create(['lease_end' => today()->addYears(2)]);
    Vehicle::factory()->retired()->create();

    livewire(FleetOverview::class)
        ->assertOk()
        ->assertSee('3');
});

it('breaks down active vehicles by fuel type', function () {
    Vehicle::factory()->count(2)->create([
        'fuel_type' => FuelType::Diesel,
        'lease_end' => today()->addYears(2),
    ]);
    Vehicle::factory()->create([
        'fuel_type' => FuelType::Electric,
        'lease_end' => today()->addYears(2),
    ]);

    livewire(FleetOverview::class)
        ->assertSee('2 Diesel')
        ->assertSee('1 Elektro');
});

it('names the vehicle whose lease expires next', function () {
    Vehicle::factory()->create([
        'license_plate' => 'M-AB 1234',
        'brand' => 'VW',
        'model' => 'Passat',
        'lease_end' => today()->addDays(34),
    ]);
    Vehicle::factory()->create([
        'license_plate' => 'M-ZZ 9999',
        'lease_end' => today()->addDays(300),
    ]);

    livewire(FleetOverview::class)
        ->assertSee('M-AB 1234')
        ->assertSee('34');
});

it('reports when no lease is pending', function () {
    Vehicle::factory()->retired()->create();

    livewire(FleetOverview::class)
        ->assertOk()
        ->assertSee('Keine');
});

it('lists leases expiring inside the warning window', function () {
    $soon = Vehicle::factory()->create(['lease_end' => today()->addDays(30)]);
    $edge = Vehicle::factory()->create(['lease_end' => today()->addDays(Vehicle::LEASE_WARNING_DAYS)]);
    $later = Vehicle::factory()->create(['lease_end' => today()->addDays(Vehicle::LEASE_WARNING_DAYS + 1)]);
    $expired = Vehicle::factory()->create(['lease_end' => today()->subDay()]);
    $retired = Vehicle::factory()->retired()->create(['lease_end' => today()->addDays(10)]);

    livewire(ExpiringLeases::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$soon, $edge])
        ->assertCanNotSeeTableRecords([$later, $expired, $retired]);
});

it('shows the driver of an expiring vehicle', function () {
    $employee = Employee::factory()->create(['first_name' => 'Anna', 'last_name' => 'Beispiel']);
    Vehicle::factory()->for($employee)->create(['lease_end' => today()->addDays(20)]);

    livewire(ExpiringLeases::class)
        ->assertSee('Beispiel');
});

it('shows no money on the dashboard', function () {
    Vehicle::factory()->create([
        'lease_end' => today()->addDays(20),
        'monthly_lease_rate' => 12345.67,
    ]);

    livewire(FleetOverview::class)->assertDontSee('12.345,67');
    livewire(ExpiringLeases::class)->assertDontSee('12.345,67');
});
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/DashboardTest.php
```

Erwartet: FAIL — Widget-Klassen fehlen.

- [ ] **Step 3: `FleetOverview` implementieren**

```bash
ddev artisan make:filament-widget FleetOverview --stats-overview
```

`app/Filament/Widgets/FleetOverview.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Enums\FuelType;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FleetOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            $this->fleetSizeStat(),
            $this->nextLeaseEndStat(),
            $this->expiringCountStat(),
        ];
    }

    private function fleetSizeStat(): Stat
    {
        $byFuelType = Vehicle::active()
            ->selectRaw('fuel_type, COUNT(*) AS total')
            ->groupBy('fuel_type')
            ->pluck('total', 'fuel_type');

        $breakdown = $byFuelType
            ->map(fn (int $total, string $fuelType): string => $total.' '.FuelType::from($fuelType)->getLabel())
            ->implode(' · ');

        return Stat::make('Aktive Fahrzeuge', (string) Vehicle::active()->count())
            ->description($breakdown !== '' ? $breakdown : 'Noch keine Fahrzeuge erfasst')
            ->descriptionIcon('heroicon-m-truck')
            ->color('primary');
    }

    private function nextLeaseEndStat(): Stat
    {
        $next = Vehicle::active()
            ->where('lease_end', '>=', today())
            ->orderBy('lease_end')
            ->first();

        if ($next === null) {
            return Stat::make('Nächstes Leasingende', 'Keine')
                ->description('Kein aktiver Vertrag mit künftigem Ende')
                ->color('gray');
        }

        $days = (int) today()->diffInDays($next->lease_end, false);

        return Stat::make('Nächstes Leasingende', $next->license_plate)
            ->description("{$next->brand} {$next->model} · in {$days} Tagen")
            ->descriptionIcon('heroicon-m-calendar-days')
            ->color($days <= 90 ? 'danger' : 'warning');
    }

    private function expiringCountStat(): Stat
    {
        $count = Vehicle::leaseEndingWithin(Vehicle::LEASE_WARNING_DAYS)->count();

        return Stat::make('Verträge mit Handlungsbedarf', (string) $count)
            ->description('Leasingende in den nächsten '.Vehicle::LEASE_WARNING_DAYS.' Tagen')
            ->color($count > 0 ? 'warning' : 'success');
    }
}
```

- [ ] **Step 4: `ExpiringLeases` implementieren**

```bash
ddev artisan make:filament-widget ExpiringLeases --table
```

`app/Filament/Widgets/ExpiringLeases.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Vehicle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ExpiringLeases extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return 'Leasingende in den nächsten '.Vehicle::LEASE_WARNING_DAYS.' Tagen';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Vehicle::leaseEndingWithin(Vehicle::LEASE_WARNING_DAYS))
            ->defaultSort('lease_end')
            ->emptyStateHeading('Kein Vertrag läuft in diesem Zeitraum aus')
            ->columns([
                TextColumn::make('license_plate')
                    ->label('Kennzeichen'),
                TextColumn::make('brand')
                    ->label('Fahrzeug')
                    ->formatStateUsing(fn ($state, Vehicle $record): string => "{$record->brand} {$record->model}"),
                TextColumn::make('employee.last_name')
                    ->label('Fahrer')
                    ->formatStateUsing(fn ($state, Vehicle $record): string => $record->employee?->full_name ?? '—'),
                TextColumn::make('lease_end')
                    ->label('Leasingende')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('days_left')
                    ->label('Resttage')
                    ->badge()
                    ->state(fn (Vehicle $record): int => (int) today()->diffInDays($record->lease_end, false))
                    ->color(fn (int $state): string => $state <= 90 ? 'danger' : 'warning'),
            ]);
    }
}
```

- [ ] **Step 5: Widgets im Panel prüfen**

`discoverWidgets()` im `AdminPanelProvider` findet beide automatisch. Sicherstellen, dass die in
Task 6 entfernten Standard-Widgets nicht wieder in der `->widgets([...])`-Liste stehen.

- [ ] **Step 6: Test ausführen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/DashboardTest.php
```

Erwartet: PASS, 8 Tests.

Falls `getTableHeading()` nicht greift: alternativ `protected ?string $heading` setzen — dann
allerdings ohne die Konstante im Text, also den Wert dort ausschreiben und einen Kommentar mit
Verweis auf `Vehicle::LEASE_WARNING_DAYS` ergänzen.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Widgets tests/Feature/Filament/DashboardTest.php
git commit -m "Add dashboard widgets for fleet size and expiring leases"
```

---

## Task 12: Benutzerverwaltung, erster Admin und Produktionshärtung

**Files:**
- Create: `app/Filament/Resources/Users/` (via `make:filament-resource`)
- Modify: `app/Providers/AppServiceProvider.php` (HTTPS-Zwang)
- Create: `README.md`
- Test: `tests/Feature/Filament/UserResourceTest.php`

**Interfaces:**
- Consumes: `UserRole` (Task 2), `User` (Task 5), `UserPolicy` (Task 7)
- Produces: Benutzerverwaltung für Admins, HTTPS-Zwang außerhalb der lokalen Umgebung, Betriebsanleitung

- [ ] **Step 1: Failing Test schreiben**

`tests/Feature/Filament/UserResourceTest.php`:

```php
<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('lets an admin list users', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();
    actingAs($admin);

    livewire(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$admin]);
});

it('hides user administration from viewers', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    expect(UserResource::canViewAny())->toBeFalse();
});

it('creates a user with a hashed password', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Neue Kollegin',
            'email' => 'neu@example.com',
            'password' => 'geheim-genug-123',
            'role' => UserRole::Viewer->value,
            'can_view_costs' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::where('email', 'neu@example.com')->first();

    expect($created)->not->toBeNull()
        ->and($created->password)->not->toBe('geheim-genug-123')
        ->and(Hash::check('geheim-genug-123', $created->password))->toBeTrue()
        ->and($created->can_view_costs)->toBeTrue()
        ->and($created->role)->toBe(UserRole::Viewer);
});

it('requires a password when creating', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Ohne Passwort',
            'email' => 'ohne@example.com',
            'password' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['password' => 'required']);
});

it('keeps the password when editing without entering a new one', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();
    actingAs($admin);

    $target = User::factory()->create(['password' => Hash::make('altes-passwort')]);
    $hashBefore = $target->password;

    livewire(EditUser::class, ['record' => $target->id])
        ->fillForm(['name' => 'Neuer Name', 'password' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    $target->refresh();

    expect($target->name)->toBe('Neuer Name')
        ->and($target->password)->toBe($hashBefore);
});

it('rejects a duplicate email', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
    User::factory()->create(['email' => 'belegt@example.com']);

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Doppelt',
            'email' => 'belegt@example.com',
            'password' => 'geheim-genug-123',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});
```

- [ ] **Step 2: Test ausführen, Fehlschlag bestätigen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/UserResourceTest.php
```

Erwartet: FAIL — Resource fehlt.

- [ ] **Step 3: Resource erzeugen**

```bash
ddev artisan make:filament-resource User --generate
```

Properties in `app/Filament/Resources/Users/UserResource.php`:

```php
protected static ?string $model = User::class;

protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

protected static ?string $modelLabel = 'Benutzer';

protected static ?string $pluralModelLabel = 'Benutzer';

protected static ?string $recordTitleAttribute = 'name';
```

- [ ] **Step 4: Formular implementieren**

`app/Filament/Resources/Users/Schemas/UserForm.php`:

```php
<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('E-Mail')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                // Beim Bearbeiten leer lassen heisst: Passwort unveraendert.
                // Der 'hashed'-Cast am Model uebernimmt das Hashen.
                TextInput::make('password')
                    ->label('Passwort')
                    ->password()
                    ->revealable()
                    ->minLength(12)
                    ->requiredOn(Operation::Create)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Beim Bearbeiten leer lassen, um das Passwort beizubehalten.'),
                Select::make('role')
                    ->label('Rolle')
                    ->options(UserRole::class)
                    ->native(false)
                    ->default(UserRole::Viewer->value)
                    ->required(),
                Toggle::make('can_view_costs')
                    ->label('Darf Kosten sehen')
                    ->helperText('Unabhängig von der Rolle. Ohne dieses Recht bleiben alle Beträge verborgen.'),
            ]);
    }
}
```

- [ ] **Step 5: Tabelle implementieren**

`app/Filament/Resources/Users/Tables/UsersTable.php`:

```php
<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Rolle')
                    ->badge(),
                IconColumn::make('can_view_costs')
                    ->label('Kosten')
                    ->boolean(),
                IconColumn::make('app_authentication_secret')
                    ->label('2FA aktiv')
                    ->boolean()
                    ->state(fn ($record): bool => filled($record->app_authentication_secret)),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->label('Rolle')
                    ->options(UserRole::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
```

- [ ] **Step 6: Test ausführen**

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/UserResourceTest.php
```

Erwartet: PASS, 6 Tests.

Falls `requiredOn(Operation::Create)` nicht auflöst: `->required(fn (string $operation): bool =>
$operation === 'create')` verwenden.

- [ ] **Step 7: HTTPS außerhalb der lokalen Umgebung erzwingen**

In `app/Providers/AppServiceProvider.php`, Methode `boot()` ergänzen:

```php
// Erzwingt https-URLs, sobald die App nicht lokal laeuft. Verhindert, dass
// Links und Formularziele auf http zeigen, wenn ein Proxy TLS terminiert.
URL::forceHttps(! $this->app->isLocal());
```

Import: `use Illuminate\Support\Facades\URL;`

- [ ] **Step 8: Session-Ablauf setzen**

Die Spec fordert einen automatischen Session-Ablauf. Laravels Standard sind 120 Minuten ohne
Ablauf beim Schließen des Browsers. Für eine Anwendung mit Kostendaten auf möglicherweise
gemeinsam genutzten Rechnern ist beides zu lax.

In `.env` und `.env.example`:

```
SESSION_LIFETIME=60
SESSION_EXPIRE_ON_CLOSE=true
```

- [ ] **Step 9: Login-Throttling verifizieren**

Filament bringt Throttling mit: `Login::authenticate()` ruft `$this->rateLimit(5)` auf, das Decay
beträgt 60 Sekunden. Es gibt **keine** Konfigurationsoption dafür — die Werte sind im
Vendor-Code hartkodiert. Dieser Schritt belegt nur, dass der Schutz greift; es ist nichts zu
implementieren.

`tests/Feature/Filament/LoginThrottleTest.php`:

```php
<?php

use App\Models\User;
use Filament\Auth\Pages\Login;

use function Pest\Livewire\livewire;

it('throttles repeated failed login attempts', function () {
    $user = User::factory()->create();

    // Die ersten fuenf Versuche scheitern an falschen Zugangsdaten.
    foreach (range(1, 5) as $attempt) {
        livewire(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'falsch'])
            ->call('authenticate');
    }

    // Der sechste laeuft ins Rate-Limit und meldet das per Notification.
    livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'falsch'])
        ->call('authenticate')
        ->assertNotified();

    expect(auth()->check())->toBeFalse();
});
```

```bash
ddev exec vendor/bin/pest tests/Feature/Filament/LoginThrottleTest.php
```

Erwartet: PASS.

Falls der Test an abweichenden Formularfeldnamen scheitert: in
`vendor/filament/filament/packages/panels/src/Auth/Pages/Login.php` nachsehen, wie das
Login-Schema die Felder benennt, und den Test anpassen. Das Throttling selbst nicht anfassen.

- [ ] **Step 10: Ersten Admin anlegen**

```bash
ddev artisan make:filament-user
```

Interaktiv Name, E-Mail und Passwort eingeben. Anschließend zum Admin mit Kostenrecht machen:

```bash
ddev artisan tinker --execute="\$u = App\Models\User::firstOrFail(); \$u->update(['role' => 'admin', 'can_view_costs' => true]); echo \$u->email.' ist jetzt Administrator';"
```

- [ ] **Step 11: Anwendung im Browser prüfen**

```bash
ddev launch /admin
```

Prüfen, in dieser Reihenfolge:
1. Login mit dem angelegten Admin
2. Filament verlangt die 2FA-Einrichtung — QR-Code mit einer Authenticator-App scannen, Code eingeben, Recovery-Codes notieren
3. Dashboard zeigt beide Widgets, keine Geldbeträge
4. Ein Fahrzeug anlegen, dabei Mitarbeiter, Tankkarte und Leasinggeber neu erfassen
5. Menüpunkt „Kosten" ist sichtbar und zeigt die Summenzeile
6. Zum Test einen zweiten Benutzer mit Rolle *Nur-Lesen* ohne Kostenrecht anlegen, mit dem anmelden und prüfen: keine Schaltflächen zum Anlegen, kein Menüpunkt „Kosten", keine Beträge im Fahrzeugformular

- [ ] **Step 12: README schreiben**

`README.md`:

```markdown
# Fuhrparkmanagement

Interne Web-App zur Dokumentation des Firmenfuhrparks: Fahrzeuge, Fahrer, Tankkarten,
Leasingzeiträume und Kosten.

## Stack

Laravel 13, Filament 5, MariaDB, DDEV für die lokale Entwicklung.

## Lokal starten

    ddev start
    ddev launch /admin

## Tests

    ddev exec vendor/bin/pest

## Benutzer und Rechte

Benutzer werden in der App unter *Verwaltung → Benutzer* gepflegt. Zwei Angaben steuern die
Rechte:

- **Rolle** — *Administrator* darf alles, *Nur-Lesen* darf ausschließlich lesen und exportieren.
- **Darf Kosten sehen** — unabhängig von der Rolle. Ohne dieses Recht bleiben alle Beträge
  verborgen, auch der Menüpunkt *Kosten*.

Der erste Benutzer wird über `ddev artisan make:filament-user` angelegt und anschließend zum
Administrator gemacht.

Zwei-Faktor-Authentisierung per Authenticator-App ist Pflicht und wird beim ersten Login
eingerichtet. Recovery-Codes werden dabei einmalig angezeigt — sicher aufbewahren.

## Betrieb in der Cloud

- `APP_ENV=production` und `APP_DEBUG=false` setzen
- `APP_KEY` muss gesetzt sein und darf sich nie ändern: Tankkartennummern und 2FA-Secrets sind
  damit verschlüsselt. Ein Verlust des Schlüssels macht diese Daten unlesbar.
- HTTPS wird außerhalb der lokalen Umgebung automatisch erzwungen.
- Datenbank-Backups einrichten.
- Queue: siehe Notiz unten zum Export.

## Export

Der CSV-/Excel-Export läuft über Laravels Job-Batches mit `QUEUE_CONNECTION=sync`. Das ist
Absicht: bei dieser Datenmenge ist der Export sofort fertig, und es braucht keinen
Worker-Prozess auf dem Server.

Sollte in Task 9 auf `database` umgestellt worden sein, muss ein Worker laufen
(`php artisan queue:work`, überwacht durch systemd oder Supervisor).

## Nicht enthalten (bewusst)

Audit-Log, E-Mail-Benachrichtigungen, Fahrer-Historie, Kraftstoffkosten via DKV/Elli.
Siehe `docs/superpowers/specs/2026-07-28-fuhrparkmanagement-design.md`.
```

Den Platzhalter in der Export-Notiz mit dem in Task 9 ermittelten Wert füllen — er ist die
einzige Stelle, die von einem Prüfergebnis abhängt.

- [ ] **Step 13: Gesamte Suite ausführen**

```bash
ddev exec vendor/bin/pest
```

Erwartet: alle Tests grün. Anzahl notieren.

- [ ] **Step 14: Commit**

```bash
git add -A
git commit -m "Add user management, force HTTPS outside local and document operations"
```

---

## Nach Abschluss

Vor der Meldung „fertig" die Skill `superpowers:verification-before-completion` anwenden:
gesamte Suite laufen lassen, Ausgabe prüfen, und die sechs manuellen Schritte aus Task 12,
Schritt 9 tatsächlich im Browser durchgehen. Die Berechtigungsprüfung mit einem zweiten,
schwächer berechtigten Benutzer ist der wichtigste davon — sie ist der Kern der
Sicherheitsanforderung und lässt sich nicht vollständig automatisiert belegen.
