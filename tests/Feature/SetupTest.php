<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the setup form when no user exists yet', function () {
    expect(User::count())->toBe(0);

    $this->get('/setup')
        ->assertOk()
        ->assertSee('Name')
        ->assertSee('Passwort');
});

it('returns 404 for the setup form once a user exists', function () {
    User::factory()->create();

    $this->get('/setup')->assertNotFound();
});

it('returns 404 and creates no user when posting setup once a user exists', function () {
    User::factory()->create();

    $response = $this->post('/setup', [
        'name' => 'Angreifer',
        'email' => 'angreifer@example.com',
        'password' => 'ganzsicheres-passwort',
        'password_confirmation' => 'ganzsicheres-passwort',
    ]);

    $response->assertNotFound();

    expect(User::where('email', 'angreifer@example.com')->exists())->toBeFalse()
        ->and(User::count())->toBe(1);
});

it('creates exactly one administrator with cost access on successful submission', function () {
    $this->post('/setup', [
        'name' => 'Erste Chefin',
        'email' => 'chefin@example.com',
        'password' => 'ausreichend-langes-passwort',
        'password_confirmation' => 'ausreichend-langes-passwort',
    ]);

    expect(User::count())->toBe(1);

    $created = User::where('email', 'chefin@example.com')->firstOrFail();

    expect($created->name)->toBe('Erste Chefin')
        ->and($created->role)->toBe(UserRole::Admin)
        ->and($created->can_view_costs)->toBeTrue();
});

it('hashes the password exactly once, so the chosen password can log in', function () {
    $this->post('/setup', [
        'name' => 'Erste Chefin',
        'email' => 'chefin@example.com',
        'password' => 'ausreichend-langes-passwort',
        'password_confirmation' => 'ausreichend-langes-passwort',
    ]);

    $created = User::where('email', 'chefin@example.com')->firstOrFail();

    expect(Hash::check('ausreichend-langes-passwort', $created->password))->toBeTrue();

    auth()->logout();

    expect(auth()->attempt([
        'email' => 'chefin@example.com',
        'password' => 'ausreichend-langes-passwort',
    ]))->toBeTrue();
});

it('logs the new user in and redirects to the dashboard after successful submission', function () {
    $response = $this->post('/setup', [
        'name' => 'Erste Chefin',
        'email' => 'chefin@example.com',
        'password' => 'ausreichend-langes-passwort',
        'password_confirmation' => 'ausreichend-langes-passwort',
    ]);

    $created = User::where('email', 'chefin@example.com')->firstOrFail();

    $response->assertRedirect('/admin');
    expect(auth()->check())->toBeTrue()
        ->and(auth()->id())->toBe($created->id);
});

it('makes setup unreachable once the first submission has succeeded', function () {
    $this->post('/setup', [
        'name' => 'Erste Chefin',
        'email' => 'chefin@example.com',
        'password' => 'ausreichend-langes-passwort',
        'password_confirmation' => 'ausreichend-langes-passwort',
    ]);

    auth()->logout();

    $this->get('/setup')->assertNotFound();
});

it('rejects a password shorter than the minimum length', function () {
    $this->post('/setup', [
        'name' => 'Zu Kurz',
        'email' => 'zukurz@example.com',
        'password' => 'kurz1234567', // 11 Zeichen, knapp unter der Grenze von 12
        'password_confirmation' => 'kurz1234567',
    ])->assertSessionHasErrors('password');

    expect(User::count())->toBe(0);
});

it('rejects a mismatched password confirmation', function () {
    $this->post('/setup', [
        'name' => 'Vertippt',
        'email' => 'vertippt@example.com',
        'password' => 'ausreichend-langes-passwort',
        'password_confirmation' => 'anderes-passwort-hier',
    ])->assertSessionHasErrors('password');

    expect(User::count())->toBe(0);
});

it('rejects an invalid email address', function () {
    $this->post('/setup', [
        'name' => 'Ungueltig',
        'email' => 'keine-email-adresse',
        'password' => 'ausreichend-langes-passwort',
        'password_confirmation' => 'ausreichend-langes-passwort',
    ])->assertSessionHasErrors('email');

    expect(User::count())->toBe(0);
});

it('rejects a submission missing required fields', function () {
    $this->post('/setup', [])
        ->assertSessionHasErrors(['name', 'email', 'password']);

    expect(User::count())->toBe(0);
});

it('keeps prior input except the passwords after a validation error', function () {
    $this->post('/setup', [
        'name' => 'Erika Musterfrau',
        'email' => 'keine-email-adresse',
        'password' => 'ausreichend-langes-passwort',
        'password_confirmation' => 'ausreichend-langes-passwort',
    ]);

    $this->assertTrue(session()->hasOldInput('name'));
    expect(session()->getOldInput('name'))->toBe('Erika Musterfrau')
        ->and(session()->getOldInput('email'))->toBe('keine-email-adresse')
        ->and(session()->hasOldInput('password'))->toBeFalse();
});

it('does not create a second administrator when setup is submitted twice in a row', function () {
    // Zwei Anfragen hintereinander: die zweite darf keinen zweiten Benutzer
    // erzeugen, egal ob sie an der fruehen 404-Pruefung oder erst an der
    // gesperrten Transaktion scheitert.
    $first = $this->post('/setup', [
        'name' => 'Erste Chefin',
        'email' => 'erste@example.com',
        'password' => 'ausreichend-langes-passwort',
        'password_confirmation' => 'ausreichend-langes-passwort',
    ]);

    $second = $this->post('/setup', [
        'name' => 'Zweite Chefin',
        'email' => 'zweite@example.com',
        'password' => 'auch-ausreichend-langes-passwort',
        'password_confirmation' => 'auch-ausreichend-langes-passwort',
    ]);

    $first->assertRedirect('/admin');
    $second->assertNotFound();

    expect(User::count())->toBe(1)
        ->and(User::where('email', 'zweite@example.com')->exists())->toBeFalse();
});
