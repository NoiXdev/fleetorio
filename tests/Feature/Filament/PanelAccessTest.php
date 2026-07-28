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

it('redirects a user without two factor set up to the enrollment page', function () {
    // Beobachtetes Verhalten (per Debug-Aufruf verifiziert): Ein angemeldeter
    // Benutzer ohne eingerichtetes 2FA wird von /admin auf die
    // Einrichtungsseite umgeleitet, statt das Dashboard zu sehen. Das ist die
    // in der Spezifikation geforderte 2FA-Pflicht - deshalb nutzen alle
    // weiteren Oberflächentests `withTwoFactor()`.
    actingAs(User::factory()->admin()->create());

    $this->get('/admin')->assertRedirect('/admin/multi-factor-authentication/set-up');
});
