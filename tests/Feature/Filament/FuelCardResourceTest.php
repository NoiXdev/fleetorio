<?php

use App\Enums\FuelCardProvider;
use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\FuelCards\Pages\CreateFuelCard;
use App\Filament\Resources\FuelCards\Pages\EditFuelCard;
use App\Filament\Resources\FuelCards\Pages\ListFuelCards;
use App\Models\FuelCard;
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
    livewire(CreateFuelCard::class)
        ->fillForm([
            'provider' => FuelCardProvider::Dkv->value,
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
        ->fillForm(['provider' => null, 'card_number' => null])
        ->call('create')
        ->assertHasFormErrors(['provider' => 'required', 'card_number' => 'required']);
});

it('filters by provider', function () {
    $dkv = FuelCard::factory()->create(['provider' => FuelCardProvider::Dkv]);
    $elli = FuelCard::factory()->create(['provider' => FuelCardProvider::Elli]);

    livewire(ListFuelCards::class)
        ->filterTable('provider', FuelCardProvider::Dkv->value)
        ->assertCanSeeTableRecords([$dkv])
        ->assertCanNotSeeTableRecords([$elli]);
});

it('does not show the full card number in the list, only provider and last four digits', function () {
    // Die Nummer ist verschluesselt und im Model als $hidden gefuehrt; die
    // Liste soll sie nicht im Klartext ausbreiten (siehe FuelCard::maskedNumber()).
    $card = FuelCard::factory()->create([
        'provider' => FuelCardProvider::Dkv,
        'card_number' => '7080999886789',
    ]);

    livewire(ListFuelCards::class)
        ->assertOk()
        ->assertSee('DKV ····6789')
        ->assertDontSee('7080999886789');
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
