<?php

use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

it('shows a password reset link on the login page', function () {
    $this->get('/admin/login')->assertSee('Passwort vergessen?');
});

it('sends a reset notification for a known user', function () {
    Notification::fake();

    $user = User::factory()->create();

    livewire(RequestPasswordReset::class)
        ->fillForm(['email' => $user->email])
        ->call('request');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('throttles repeated password reset requests', function () {
    $user = User::factory()->create();

    // Die ersten zwei Anfragen sind erlaubt (Filaments eingebautes Limit).
    foreach (range(1, 2) as $attempt) {
        livewire(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request');
    }

    // Die dritte Anfrage laeuft ins Rate-Limit und meldet das per Notification.
    livewire(RequestPasswordReset::class)
        ->fillForm(['email' => $user->email])
        ->call('request')
        ->assertNotified();
});
