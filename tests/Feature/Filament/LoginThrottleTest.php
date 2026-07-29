<?php

use App\Models\User;
use Filament\Auth\Pages\Login;

use function Pest\Livewire\livewire;

it('throttles repeated failed login attempts', function () {
    $user = User::factory()->create();

    // Die ersten fuenf Versuche scheitern an falschen Zugangsdaten.
    foreach (range(1, 5) as $attempt) {
        livewire(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'falsch'])
            ->call('authenticate');
    }

    // Der sechste laeuft ins Rate-Limit und meldet das per Notification.
    livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'falsch'])
        ->call('authenticate')
        ->assertNotified();

    expect(auth()->check())->toBeFalse();
});
