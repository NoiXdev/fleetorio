<?php

use App\Filament\Pages\AppearanceSettings;
use App\Models\AppSetting;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('probe: saving a colour', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    livewire(AppearanceSettings::class)
        ->fillForm(['primary_color' => '#bb0000'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(AppSetting::query()->first()?->primary_color)->toBe('#bb0000');
});
