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
