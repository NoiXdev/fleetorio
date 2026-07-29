<?php

use App\Enums\FuelType;
use App\Models\Employee;
use App\Models\Vehicle;

it('serves the public overview page to guests', function () {
    $this->get('/')->assertOk();
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

it('reveals nothing about the fleet to anonymous visitors', function () {
    // Die Startseite ist ohne Anmeldung erreichbar und zeigt deshalb keinerlei
    // Bestandsdaten - auch keine aggregierten. Selbst die Fuhrparkgroesse waere
    // eine Aussage ueber das Unternehmen, die dort nicht hingehoert.
    $employee = Employee::factory()->create([
        'first_name' => 'Geheim',
        'last_name' => 'Vertraulich',
    ]);

    Vehicle::factory()->for($employee)->create([
        'license_plate' => 'M-XX 9999',
        'brand' => 'Testmarke',
        'fuel_type' => FuelType::Electric,
        'monthly_lease_rate' => 987.65,
    ]);

    $response = $this->get('/')->assertOk();

    $response
        ->assertDontSee('M-XX 9999')
        ->assertDontSee('Geheim')
        ->assertDontSee('Vertraulich')
        ->assertDontSee('Testmarke')
        ->assertDontSee('987')
        ->assertDontSee('Elektro')
        ->assertDontSee('Aktive Fahrzeuge')
        ->assertDontSee('Antriebsart');

    // Die Anzahl darf auch dann nicht auftauchen, wenn sie zufaellig als
    // Ziffer irgendwo im Markup stuende: kein Fahrzeugbestand, keine Zahl.
    expect($response->getContent())->not->toContain('Fahrzeuge erfasst');
});
