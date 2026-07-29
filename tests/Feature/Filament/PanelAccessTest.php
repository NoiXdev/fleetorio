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

it('lets a user without two factor set up reach the dashboard directly', function () {
    // Beobachtetes Verhalten (per Debug-Aufruf verifiziert): Seit die
    // 2FA-Pflicht auf Wunsch des Auftraggebers auf isRequired: false gestellt
    // wurde, erzwingt Filament die Einrichtungsseite nicht mehr. Ein
    // angemeldeter Benutzer ohne eingerichtetes 2FA erreicht /admin direkt
    // (200 statt 302 zur Einrichtungsseite). Die Einrichtung bleibt über die
    // Profilseite weiterhin freiwillig möglich - die vielen Tests mit
    // `withTwoFactor()` dokumentieren weiterhin den abgesicherten Fall.
    actingAs(User::factory()->admin()->create());

    $this->get('/admin')->assertOk();
});
