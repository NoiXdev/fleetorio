<?php

use App\Filament\Resources\Dealerships\Pages\EditDealership;
use App\Filament\Resources\Dealerships\Pages\ListDealerships;
use App\Filament\Resources\Dealerships\Pages\ViewDealership;
use App\Models\Dealership;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Actions\EditAction;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('lets a read-only user reach the dealership view page', function () {
    // Der eigentliche Gewinn dieser Aufgabe: bisher endete dieser Aufruf mit
    // 403, weil es nur die Bearbeitungsseite gab (DealershipPolicy::update).
    // DealershipPolicy::view() gibt schon immer true zurueck.
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $dealership = Dealership::factory()->create();

    $this->get("/admin/dealerships/{$dealership->id}")->assertOk();

    livewire(ViewDealership::class, ['record' => $dealership->id])
        ->assertOk();
});

it('sends a table row click to the view page automatically once it is registered', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $dealership = Dealership::factory()->create();

    $table = livewire(ListDealerships::class)->instance()->getTable();

    expect($table->getRecordUrl($dealership))
        ->toBe(ViewDealership::getUrl(['record' => $dealership]));
});

it('shows email, street, postcode and notes on the view page, which are otherwise unreachable for a read-only user', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $dealership = Dealership::factory()->create([
        'email' => 'service@autohaus-muster.example.com',
        'street' => 'Musterstraße 12',
        'postcode' => '12345',
        'city' => 'Musterstadt',
        'notes' => 'Nur nach Terminvereinbarung.',
    ]);

    livewire(ViewDealership::class, ['record' => $dealership->id])
        ->assertOk()
        ->assertSee('service@autohaus-muster.example.com')
        ->assertSee('Musterstraße 12')
        ->assertSee('12345')
        ->assertSee('Nur nach Terminvereinbarung.');
});

it('renders the address as one connected block, not three separate fields', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $dealership = Dealership::factory()->create([
        'street' => 'Musterstraße 12',
        'postcode' => '12345',
        'city' => 'Musterstadt',
    ]);

    $component = livewire(ViewDealership::class, ['record' => $dealership->id])
        ->assertOk();

    // Postleitzahl und Ort stehen zusammen in einer Zeile ("12345
    // Musterstadt"), nicht in getrennten Feldern - genau diese Zeichenkette
    // kaeme nur zustande, wenn beide im selben Textknoten landen.
    $component->assertSee('12345 Musterstadt');

    // Und es gibt kein eigenes Infolist-Feld je Adressbestandteil, nur das
    // zusammengefasste "address"-Feld.
    $component
        ->assertSchemaComponentExists('address')
        ->assertSchemaComponentDoesNotExist('street')
        ->assertSchemaComponentDoesNotExist('postcode')
        ->assertSchemaComponentDoesNotExist('city');
});

it('shows the vehicle count on the view page', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $dealership = Dealership::factory()->create();
    Vehicle::factory()->for($dealership)->count(5)->create();

    livewire(ViewDealership::class, ['record' => $dealership->id])
        ->assertOk()
        ->assertSeeInOrder(['Fahrzeuge', '5']);
});

it('renders the view page without crashing when every optional field is empty, including the whole address', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $dealership = Dealership::factory()->create([
        'contact_person' => null,
        'phone' => null,
        'email' => null,
        'street' => null,
        'postcode' => null,
        'city' => null,
        'notes' => null,
    ]);

    livewire(ViewDealership::class, ['record' => $dealership->id])
        ->assertOk()
        ->assertSee('—');
});

describe('the edit button', function () {
    it('is hidden from a read-only user', function () {
        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $dealership = Dealership::factory()->create();

        livewire(ViewDealership::class, ['record' => $dealership->id])
            ->assertActionHidden(EditAction::class);
    });

    it('leads an admin to the dedicated edit page, not a modal', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $dealership = Dealership::factory()->create();

        livewire(ViewDealership::class, ['record' => $dealership->id])
            ->assertActionVisible(EditAction::class)
            ->assertActionHasUrl(EditAction::class, EditDealership::getUrl(['record' => $dealership->id]));
    });
});
