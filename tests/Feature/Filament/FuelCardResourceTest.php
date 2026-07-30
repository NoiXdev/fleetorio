<?php

use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\FuelCards\Pages\CreateFuelCard;
use App\Filament\Resources\FuelCards\Pages\EditFuelCard;
use App\Filament\Resources\FuelCards\Pages\ListFuelCards;
use App\Models\FuelCard;
use App\Models\FuelCardProvider;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
});

it('lists fuel cards', function () {
    $cards = FuelCard::factory()->count(3)->create();

    livewire(ListFuelCards::class)
        ->assertOk()
        ->assertCanSeeTableRecords($cards);
});

it('creates a fuel card and stores the number encrypted', function () {
    $provider = FuelCardProvider::factory()->create();

    livewire(CreateFuelCard::class)
        ->fillForm([
            'fuel_card_provider_id' => $provider->id,
            'card_number' => '7080999888777',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $card = FuelCard::first();
    expect($card->card_number)->toBe('7080999888777');

    $raw = DB::table('fuel_cards')->where('id', $card->id)->value('card_number');
    expect($raw)->not->toBe('7080999888777');
});

it('requires provider and card number', function () {
    livewire(CreateFuelCard::class)
        ->fillForm(['fuel_card_provider_id' => null, 'card_number' => null])
        ->call('create')
        ->assertHasFormErrors(['fuel_card_provider_id' => 'required', 'card_number' => 'required']);
});

it('filters by provider', function () {
    $dkv = FuelCard::factory()->create(['fuel_card_provider_id' => FuelCardProvider::where('name', 'DKV')->value('id')]);
    $elli = FuelCard::factory()->create(['fuel_card_provider_id' => FuelCardProvider::where('name', 'Elli')->value('id')]);

    livewire(ListFuelCards::class)
        ->filterTable('fuel_card_provider_id', FuelCardProvider::where('name', 'DKV')->value('id'))
        ->assertCanSeeTableRecords([$dkv])
        ->assertCanNotSeeTableRecords([$elli]);
});

it('shows the full card number in the list', function () {
    // Auftraggeber-Entscheidung: Die Zuordnung war mit der maskierten Nummer
    // umstaendlich. Beide Stellen (Tankkartenliste, Fahrzeugformular) sind
    // ohnehin nur angemeldeten Nutzern zugaenglich; der encrypted-Cast am
    // Model schuetzt weiterhin die Datenbank, nicht die Anzeige.
    $card = FuelCard::factory()->create([
        'card_number' => '7080999886789',
    ]);

    livewire(ListFuelCards::class)
        ->assertOk()
        ->assertSee('7080999886789');
});

it('shows the provider name in the list', function () {
    $provider = FuelCardProvider::factory()->create(['name' => 'Testanbieter']);
    FuelCard::factory()->create(['fuel_card_provider_id' => $provider->id]);

    livewire(ListFuelCards::class)
        ->assertOk()
        ->assertSee('Testanbieter');
});

it('refuses to delete a card that is still assigned', function () {
    $card = FuelCard::factory()->create();
    Vehicle::factory()->for($card)->create(['license_plate' => 'M-CD 5678']);

    livewire(EditFuelCard::class, ['record' => $card->id])
        ->callAction(GuardedDeleteAction::class)
        ->assertNotified();

    assertDatabaseHas(FuelCard::class, ['id' => $card->id]);
});

it('denies a viewer direct http access to the create-fuel-card page', function () {
    // Nicht nur die Navigation ausgeblendet, sondern auch per direktem Request
    // gesperrt (siehe FuelCardPolicy::create).
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $this->get('/admin/fuel-cards/create')->assertForbidden();
});
