<?php

use App\Enums\UserRole;
use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('does not expose role or cost access on the profile page', function () {
    $user = User::factory()->viewer()->withTwoFactor()->create();

    actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertFormFieldDoesNotExist('role')
        ->assertFormFieldDoesNotExist('can_view_costs');
});

it('prevents a viewer from escalating privileges through the profile page', function () {
    $user = User::factory()->viewer()->withTwoFactor()->create();

    actingAs($user);

    // Versuch, sich per Massenzuweisung über die Profilseite zum Administrator
    // mit Kostenrecht zu machen. Da `role` und `can_view_costs` keine Felder
    // des Profil-Formulars sind, landen sie nicht in $form->getState() und
    // damit nicht im $record->update($data)-Aufruf von EditProfile::save().
    Livewire::test(EditProfile::class)
        ->fillForm([
            'role' => 'admin',
            'can_view_costs' => true,
        ])
        ->call('save');

    expect($user->refresh())
        ->role->toBe(UserRole::Viewer)
        ->can_view_costs->toBeFalse();
});
