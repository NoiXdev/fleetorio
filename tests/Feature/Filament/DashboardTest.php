<?php

use App\Enums\FuelType;
use App\Filament\Widgets\ExpiringLeases;
use App\Filament\Widgets\FleetOverview;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget\Stat;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
});

/**
 * Mountet FleetOverview via Livewire (identisch zum Dashboard-Rendering) und
 * liest die Stats über Reflection aus getStats() aus. Das prüft die Kennzahl
 * anhand ihres Labels statt anhand eines gerenderten Zahlenstrings, der
 * anderswo auf der Seite zufällig auch auftauchen könnte (z.B. in einem
 * Kennzeichen oder einer Tagesangabe).
 *
 * @return array<Stat>
 */
function fleetOverviewStats(): array
{
    $widget = livewire(FleetOverview::class)->instance();

    $method = new ReflectionMethod($widget, 'getStats');
    $method->setAccessible(true);

    return $method->invoke($widget);
}

function findStatByLabel(array $stats, string $label): Stat
{
    $stat = collect($stats)->first(fn (Stat $stat): bool => $stat->getLabel() === $label);

    expect($stat)->not->toBeNull("Keine Kennzahl mit Label '{$label}' gefunden.");

    return $stat;
}

it('renders the dashboard', function () {
    $this->get('/admin')->assertOk();
});

it('counts only active vehicles', function () {
    Vehicle::factory()->count(3)->create(['lease_end' => today()->addYears(2)]);
    Vehicle::factory()->retired()->create();

    $stat = findStatByLabel(fleetOverviewStats(), 'Aktive Fahrzeuge');

    expect($stat->getValue())->toBe('3');
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

it('sorts the fuel type breakdown by count, then by enum order on ties', function () {
    // Diesel hat eine eindeutig höchste Anzahl (3) und muss zuerst stehen.
    // Benzin und Elektro sind mit je 1 gleichauf - der Tiebreak ist die
    // Deklarationsreihenfolge der Enum (Petrol vor Electric), nicht die
    // Erstellungsreihenfolge der Fahrzeuge (hier absichtlich Electric zuerst
    // angelegt, um das zu erzwingen).
    Vehicle::factory()->create(['fuel_type' => FuelType::Electric, 'lease_end' => today()->addYears(2)]);
    Vehicle::factory()->count(3)->create(['fuel_type' => FuelType::Diesel, 'lease_end' => today()->addYears(2)]);
    Vehicle::factory()->create(['fuel_type' => FuelType::Petrol, 'lease_end' => today()->addYears(2)]);

    $stat = findStatByLabel(fleetOverviewStats(), 'Aktive Fahrzeuge');

    expect($stat->getDescription())->toBe('3 Diesel · 1 Benzin · 1 Elektro');
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

it('flags urgency when the next lease ends within 90 days', function () {
    Vehicle::factory()->create(['lease_end' => today()->addDays(45)]);

    $stat = findStatByLabel(fleetOverviewStats(), 'Nächstes Leasingende');

    expect($stat->getColor())->toBe('danger');
});

it('flags a warning when the next lease ends inside the window but beyond 90 days', function () {
    Vehicle::factory()->create(['lease_end' => today()->addDays(Vehicle::LEASE_WARNING_DAYS - 10)]);

    $stat = findStatByLabel(fleetOverviewStats(), 'Nächstes Leasingende');

    expect($stat->getColor())->toBe('warning');
});

it('does not flag urgency when the next lease is far beyond the warning window', function () {
    Vehicle::factory()->create(['lease_end' => today()->addDays(Vehicle::LEASE_WARNING_DAYS + 30)]);

    $stat = findStatByLabel(fleetOverviewStats(), 'Nächstes Leasingende');

    expect($stat->getColor())->toBe('success');
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
