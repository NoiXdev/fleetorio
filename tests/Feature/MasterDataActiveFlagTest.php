<?php

use App\Filament\Resources\Dealerships\Pages\EditDealership;
use App\Models\Dealership;
use App\Models\FuelCard;
use App\Models\FuelCardProvider;
use App\Models\LeasingCompany;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

/**
 * Der Zustand "aktiv/inaktiv" gab es bisher nur am Mitarbeiter
 * (employees.active) und - unter anderem Namen - am Fahrzeug
 * (vehicles.status). Damit die Regel "loeschen nur, wenn inaktiv" fuer alle
 * Stammdaten gilt, brauchen die uebrigen vier dasselbe Feld.
 */
$masterDataModels = [
    'Autohaus' => Dealership::class,
    'Leasinggeber' => LeasingCompany::class,
    'Tankkarte' => FuelCard::class,
    'Tankkartenanbieter' => FuelCardProvider::class,
];

// Standard "aktiv": Datensaetze, die es vor dieser Migration schon gab,
// muessen unveraendert benutzbar bleiben.
it('creates master data as active by default', function (string $modelClass) {
    expect($modelClass::factory()->create()->active)->toBeTrue();
})->with($masterDataModels);

it('starts a freshly instantiated model as active, without touching the database', function (string $modelClass) {
    expect((new $modelClass)->active)->toBeTrue();
})->with($masterDataModels);

it('casts the active flag to a boolean', function (string $modelClass) {
    $record = $modelClass::factory()->create(['active' => false]);

    expect($record->fresh()->active)->toBeFalse();
})->with($masterDataModels);

// Der Modelltest oben wuerde auch dann bestehen, wenn 'active' nicht in
// $fillable stuende - die Factory umgeht die Massenzuweisung. Dieser Test
// laeuft ueber das Formular und faellt deshalb auf, was der andere durchlaesst.
it('saves the toggle through the form', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    // Feste Werte statt der Faktor-Vorgaben: Gegenstand dieses Tests ist der
    // Schalter, nicht was Faker in die uebrigen Felder schreibt. Mit
    // Zufallsdaten haengt das Ergebnis an der Validierung von sieben Feldern,
    // die hier niemand pruefen will.
    $dealership = Dealership::factory()->create([
        'active' => true,
        'name' => 'Autohaus Beispiel',
        'contact_person' => 'Anna Beispiel',
        'phone' => '089 123456',
        'email' => 'kontakt@autohaus-beispiel.example',
        'street' => 'Musterweg 1',
        'postcode' => '80331',
        'city' => 'München',
    ]);

    $component = livewire(EditDealership::class, ['record' => $dealership->getRouteKey()])
        ->fillForm(['active' => false])
        ->call('save');

    // Statt assertHasNoFormErrors(): benennt im Fehlerfall das Feld und die
    // Meldung, anstatt nur "hat Fehler" zu sagen.
    expect($component->instance()->getErrorBag()->messages())->toBe([]);

    expect($dealership->fresh()->active)->toBeFalse();
});
