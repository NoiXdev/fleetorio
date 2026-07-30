<?php

use App\Models\Dealership;
use App\Models\Employee;
use App\Models\FuelCard;
use App\Models\FuelCardProvider;
use App\Models\LeasingCompany;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Livewire\GlobalSearch;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('finds a vehicle via its license plate', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $vehicle = Vehicle::factory()->create(['license_plate' => 'M-XY 7531']);

    livewire(GlobalSearch::class)
        ->set('search', 'M-XY 7531')
        ->assertSee($vehicle->license_plate);
});

it('finds a vehicle via the name of its driver', function () {
    // Der eigentliche Wunsch aus der Spezifikation: nach einem Mitarbeiter
    // suchen und dessen Fahrzeug findet man.
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $employee = Employee::factory()->create(['first_name' => 'Berta', 'last_name' => 'Suchbeispiel']);
    $vehicle = Vehicle::factory()->for($employee)->create();
    $other = Vehicle::factory()->create();

    $result = livewire(GlobalSearch::class)
        ->set('search', 'Suchbeispiel')
        ->instance()
        ->getResults();

    $titles = $result->getCategories()->flatten()->pluck('title');

    expect($titles)->toContain($vehicle->license_plate)
        ->and($titles)->not->toContain($other->license_plate);
});

it('finds an employee via first or last name', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $employee = Employee::factory()->create(['first_name' => 'Annastasia', 'last_name' => 'Beispielmann']);

    livewire(GlobalSearch::class)
        ->set('search', 'Annastasia')
        ->assertSee('Annastasia Beispielmann');

    livewire(GlobalSearch::class)
        ->set('search', 'Beispielmann')
        ->assertSee('Annastasia Beispielmann');
});

it('finds the other master data records by name', function (string $model, string $factoryName) {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $record = $model::factory()->create(['name' => $factoryName]);

    livewire(GlobalSearch::class)
        ->set('search', $factoryName)
        ->assertSee($record->name);
})->with([
    'Autohaus' => [Dealership::class, 'Suchautohaus'],
    'Leasinggeber' => [LeasingCompany::class, 'Suchleasing'],
    'Tankkartenanbieter' => [FuelCardProvider::class, 'Suchtankkarte'],
]);

it('lets an admin find user accounts via global search', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $target = User::factory()->admin()->withTwoFactor()->create(['name' => 'Zielkonto Suchtest']);

    livewire(GlobalSearch::class)
        ->set('search', 'Zielkonto Suchtest')
        ->assertSee('Zielkonto Suchtest');
});

it('never searches the encrypted fuel card number', function () {
    // Siehe CLAUDE.md: die Kartennummer ist verschluesselt und deshalb nicht
    // durchsuchbar - die Suche liefe gegen das Chiffrat und faende nichts.
    // FuelCardResource setzt daher bewusst kein $recordTitleAttribute, wodurch
    // Resource::getGloballySearchableAttributes() eine leere Liste liefert
    // und canGloballySearch() die Ressource komplett ausschliesst.
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $card = FuelCard::factory()->create(['card_number' => '7080555444333']);

    livewire(GlobalSearch::class)
        ->set('search', '7080555444333')
        ->assertDontSee($card->card_number);
});

it('does not let a read-only user find user accounts via global search', function () {
    // Benutzerverwaltung ist Administratoren vorbehalten (UserPolicy::viewAny).
    // Filament::canGloballySearch() prueft dieselbe Policy
    // (Resource::canAccess() -> canViewAny()) - die globale Suche darf hier
    // also nichts liefern, ohne dass diese Anwendung selbst etwas dafuer tun
    // muss.
    $target = User::factory()->admin()->withTwoFactor()->create(['name' => 'Zielkonto Suchtest']);

    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    livewire(GlobalSearch::class)
        ->set('search', 'Zielkonto Suchtest')
        ->assertDontSee('Zielkonto Suchtest')
        ->assertDontSee('Benutzer');
});
