<?php

use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\Dealerships\Pages\CreateDealership;
use App\Filament\Resources\Dealerships\Pages\EditDealership;
use App\Filament\Resources\Dealerships\Pages\ListDealerships;
use App\Models\Dealership;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
});

it('lists dealerships', function () {
    $dealerships = Dealership::factory()->count(3)->create();

    livewire(ListDealerships::class)
        ->assertOk()
        ->assertCanSeeTableRecords($dealerships);
});

it('creates a dealership', function () {
    livewire(CreateDealership::class)
        ->fillForm([
            'name' => 'Autohaus Muster',
            'contact_person' => 'Frau Beispiel',
            'city' => 'München',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Dealership::class, ['name' => 'Autohaus Muster', 'city' => 'München']);
});

it('requires a name', function () {
    livewire(CreateDealership::class)
        ->fillForm(['name' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('allows a dealership without any address details', function () {
    livewire(CreateDealership::class)
        ->fillForm(['name' => 'Autohaus Minimal'])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Dealership::class, ['name' => 'Autohaus Minimal', 'street' => null]);
});

it('refuses to delete a dealership that is still referenced', function () {
    $dealership = Dealership::factory()->create();
    Vehicle::factory()->for($dealership)->create(['license_plate' => 'M-GH 3456']);

    livewire(EditDealership::class, ['record' => $dealership->id])
        ->callAction(GuardedDeleteAction::class)
        ->assertNotified();

    assertDatabaseHas(Dealership::class, ['id' => $dealership->id]);
});

it('denies a viewer direct http access to the create-dealership page', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $this->get('/admin/dealerships/create')->assertForbidden();
});
