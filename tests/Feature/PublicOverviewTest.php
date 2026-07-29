<?php

use App\Enums\FuelType;
use App\Models\Employee;
use App\Models\Vehicle;

it('serves the public overview page to guests', function () {
    $this->get('/')->assertOk();
});

it('shows the active vehicle count and fuel type breakdown', function () {
    Vehicle::factory()->count(2)->create(['fuel_type' => FuelType::Diesel]);
    Vehicle::factory()->create(['fuel_type' => FuelType::Electric]);
    Vehicle::factory()->retired()->create(['fuel_type' => FuelType::Petrol]);

    // Die Zahl im Kontext ihrer Beschriftung pruefen, statt an eine konkrete
    // CSS-Klasse zu binden - sonst bricht der Test bei jeder Umgestaltung,
    // ohne dass sich am Verhalten etwas geaendert haette.
    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['Aktive Fahrzeuge', '3'], escape: false)
        ->assertSee('2 Diesel')
        ->assertSee('1 Elektro');
});

it('renders without errors when no vehicle is recorded', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Noch keine Fahrzeuge erfasst');
});

it('shows a prominent login link to the admin panel', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Anmelden')
        ->assertSee('/admin/login', escape: false);
});

it('shows the company logo and uses it as favicon', function () {
    // Das Logo liegt lokal unter public/images - kein Verweis auf www.3b.de,
    // damit die Seite ohne Verbindung nach draussen vollstaendig laedt.
    $this->get('/')
        ->assertOk()
        ->assertSee('images/a3b-logo.svg', escape: false)
        ->assertSee('rel="icon"', escape: false)
        ->assertDontSee('www.3b.de');
});

it('never leaks license plates or employee names to the public overview page', function () {
    $employee = Employee::factory()->create([
        'first_name' => 'Geheim',
        'last_name' => 'Vertraulich',
    ]);

    Vehicle::factory()->for($employee)->create([
        'license_plate' => 'M-XX 9999',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('M-XX 9999')
        ->assertDontSee('Geheim')
        ->assertDontSee('Vertraulich');
});
