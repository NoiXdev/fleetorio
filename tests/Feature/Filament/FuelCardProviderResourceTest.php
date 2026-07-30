<?php

use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\FuelCardProviders\Pages\CreateFuelCardProvider;
use App\Filament\Resources\FuelCardProviders\Pages\EditFuelCardProvider;
use App\Filament\Resources\FuelCardProviders\Pages\ListFuelCardProviders;
use App\Models\FuelCard;
use App\Models\FuelCardProvider;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
});

it('lists fuel card providers, including the starting values from the migration', function () {
    // Die Startwerte (DKV, UTA, Aral, ...) sind Teil der Migration
    // 2026_07_30_180000_create_fuel_card_providers_table, nicht dieses Tests -
    // sie muessen also bereits vorhanden sein, sobald die Datenbank steht.
    livewire(ListFuelCardProviders::class)
        ->assertOk()
        ->assertSee('DKV')
        ->assertSee('Elli')
        ->assertSee('Sonstige');
});

it('creates a fuel card provider', function () {
    livewire(CreateFuelCardProvider::class)
        ->fillForm(['name' => 'Testanbieter', 'notes' => 'Nur für Tests'])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(FuelCardProvider::class, ['name' => 'Testanbieter', 'notes' => 'Nur für Tests']);
});

it('requires a unique name', function () {
    FuelCardProvider::factory()->create(['name' => 'Testanbieter']);

    livewire(CreateFuelCardProvider::class)
        ->fillForm(['name' => 'Testanbieter'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

it('refuses to delete a provider that is still assigned to a card', function () {
    $provider = FuelCardProvider::factory()->create();
    FuelCard::factory()->create(['fuel_card_provider_id' => $provider->id]);

    livewire(EditFuelCardProvider::class, ['record' => $provider->id])
        ->callAction(GuardedDeleteAction::class)
        ->assertNotified();

    assertDatabaseHas(FuelCardProvider::class, ['id' => $provider->id]);
});

it('allows deleting a provider that is not assigned to any card', function () {
    $provider = FuelCardProvider::factory()->create();

    livewire(EditFuelCardProvider::class, ['record' => $provider->id])
        ->callAction(GuardedDeleteAction::class);

    expect(FuelCardProvider::find($provider->id))->toBeNull();
});

it('denies a viewer direct http access to the create-fuel-card-provider page', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $this->get('/admin/fuel-card-providers/create')->assertForbidden();
});
