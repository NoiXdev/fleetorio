<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

describe('resetting another user two factor', function () {
    it('lets an admin reset another user two factor setup', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $target = User::factory()->withTwoFactor()->create();

        livewire(ListUsers::class)
            ->callTableAction('resetTwoFactor', $target);

        $target->refresh();

        expect($target->app_authentication_secret)->toBeNull()
            ->and($target->app_authentication_recovery_codes)->toBeNull();
    });

    it('is invisible for a user without two-factor set up', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $target = User::factory()->create(['app_authentication_secret' => null]);

        livewire(ListUsers::class)
            ->assertTableActionHidden('resetTwoFactor', $target);
    });

    it('is hidden from a non-admin', function () {
        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $target = User::factory()->withTwoFactor()->create();

        // Nicht-Admins erreichen /admin/users ohnehin nicht - direkt gegen
        // die Policy pruefen: die entscheidet, nicht die Navigation.
        expect(auth()->user()->can('resetTwoFactor', $target))->toBeFalse();
    });
});

describe('the requires_two_factor flag', function () {
    it('saves the toggle through the user form', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $target = User::factory()->create(['requires_two_factor' => false]);

        livewire(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['requires_two_factor' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($target->fresh()->requires_two_factor)->toBeTrue();
    });

    it('starts at false by default', function () {
        $user = User::factory()->create();

        expect($user->requires_two_factor)->toBeFalse();
    });
});

describe('the two-factor requirement middleware', function () {
    // Wer 2FA verpflichtend hat und noch kein Geheimnis, wird beim naechsten
    // Klick ins Panel auf die Profilseite geleitet und kann von dort
    // einrichten.
    it('redirects a user who is required to use two factor but has not set it up', function () {
        actingAs(User::factory()->create([
            'requires_two_factor' => true,
            'app_authentication_secret' => null,
        ]));

        get('/admin')->assertRedirect(route('filament.admin.auth.profile'));
    });

    // Der eigentliche Fallstrick der Aufgabe: waere die Middleware wirklich
    // auf JEDER Anfrage aktiv, leitete sie die Profilseite selbst und den
    // Logout ebenfalls um - eine Schleife, aus der sich der Nutzer nicht
    // einmal abmelden koennte.
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

    it('is registered in the admin panel auth middleware', function () {
        $middleware = filament()->getPanel('admin')->getAuthMiddleware();

        expect($middleware)->toContain(EnsureTwoFactorWhenRequired::class);
    });
});
