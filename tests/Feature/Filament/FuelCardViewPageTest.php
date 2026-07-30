<?php

use App\Filament\Resources\FuelCards\Pages\EditFuelCard;
use App\Filament\Resources\FuelCards\Pages\ListFuelCards;
use App\Filament\Resources\FuelCards\Pages\ViewFuelCard;
use App\Models\FuelCard;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Actions\EditAction;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('lets a read-only user reach the fuel card view page', function () {
    // Der eigentliche Gewinn dieser Aufgabe: bisher endete dieser Aufruf mit
    // 403, weil es nur die Bearbeitungsseite gab (FuelCardPolicy::update).
    // FuelCardPolicy::view() gibt schon immer true zurueck.
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $fuelCard = FuelCard::factory()->create();

    $this->get("/admin/fuel-cards/{$fuelCard->id}")->assertOk();

    livewire(ViewFuelCard::class, ['record' => $fuelCard->id])
        ->assertOk();
});

it('sends a table row click to the view page automatically once it is registered', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $fuelCard = FuelCard::factory()->create();

    $table = livewire(ListFuelCards::class)->instance()->getTable();

    expect($table->getRecordUrl($fuelCard))
        ->toBe(ViewFuelCard::getUrl(['record' => $fuelCard]));
});

it('shows the notes on the view page, which are otherwise unreachable for a read-only user', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $fuelCard = FuelCard::factory()->create(['notes' => 'Nur für Diesel-Fahrzeuge freigegeben.']);

    livewire(ViewFuelCard::class, ['record' => $fuelCard->id])
        ->assertOk()
        ->assertSee('Nur für Diesel-Fahrzeuge freigegeben.');
});

it('shows the full, unmasked card number, same as the list', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $fuelCard = FuelCard::factory()->create(['card_number' => '7080123456789']);

    livewire(ViewFuelCard::class, ['record' => $fuelCard->id])
        ->assertOk()
        ->assertSee('7080123456789');
});

it('shows the vehicle count on the view page', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $fuelCard = FuelCard::factory()->create();
    Vehicle::factory()->for($fuelCard)->count(2)->create();

    livewire(ViewFuelCard::class, ['record' => $fuelCard->id])
        ->assertOk()
        ->assertSeeInOrder(['Fahrzeuge', '2']);
});

it('renders the view page without crashing when every optional field is empty', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $fuelCard = FuelCard::factory()->create([
        'valid_until' => null,
        'notes' => null,
    ]);

    livewire(ViewFuelCard::class, ['record' => $fuelCard->id])
        ->assertOk()
        ->assertSee('—');
});

describe('the edit button', function () {
    it('is hidden from a read-only user', function () {
        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $fuelCard = FuelCard::factory()->create();

        livewire(ViewFuelCard::class, ['record' => $fuelCard->id])
            ->assertActionHidden(EditAction::class);
    });

    it('leads an admin to the dedicated edit page, not a modal', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $fuelCard = FuelCard::factory()->create();

        livewire(ViewFuelCard::class, ['record' => $fuelCard->id])
            ->assertActionVisible(EditAction::class)
            ->assertActionHasUrl(EditAction::class, EditFuelCard::getUrl(['record' => $fuelCard->id]));
    });
});
