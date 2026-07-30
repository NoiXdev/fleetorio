<?php

use App\Filament\Resources\LeasingCompanies\Pages\EditLeasingCompany;
use App\Filament\Resources\LeasingCompanies\Pages\ListLeasingCompanies;
use App\Filament\Resources\LeasingCompanies\Pages\ViewLeasingCompany;
use App\Models\LeasingCompany;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Actions\EditAction;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('lets a read-only user reach the leasing company view page', function () {
    // Der eigentliche Gewinn dieser Aufgabe: bisher endete dieser Aufruf mit
    // 403, weil es nur die Bearbeitungsseite gab (LeasingCompanyPolicy::
    // update). LeasingCompanyPolicy::view() gibt schon immer true zurueck.
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $leasingCompany = LeasingCompany::factory()->create();

    $this->get("/admin/leasing-companies/{$leasingCompany->id}")->assertOk();

    livewire(ViewLeasingCompany::class, ['record' => $leasingCompany->id])
        ->assertOk();
});

it('sends a table row click to the view page automatically once it is registered', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $leasingCompany = LeasingCompany::factory()->create();

    $table = livewire(ListLeasingCompanies::class)->instance()->getTable();

    expect($table->getRecordUrl($leasingCompany))
        ->toBe(ViewLeasingCompany::getUrl(['record' => $leasingCompany]));
});

it('shows email and notes on the view page, which are otherwise unreachable for a read-only user', function () {
    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $leasingCompany = LeasingCompany::factory()->create([
        'email' => 'kontakt@vw-leasing.example.com',
        'notes' => 'Rahmenvertrag Nr. 4711.',
    ]);

    livewire(ViewLeasingCompany::class, ['record' => $leasingCompany->id])
        ->assertOk()
        ->assertSee('kontakt@vw-leasing.example.com')
        ->assertSee('Rahmenvertrag Nr. 4711.');
});

it('shows the vehicle count on the view page', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $leasingCompany = LeasingCompany::factory()->create();
    Vehicle::factory()->for($leasingCompany)->count(4)->create();

    livewire(ViewLeasingCompany::class, ['record' => $leasingCompany->id])
        ->assertOk()
        ->assertSeeInOrder(['Fahrzeuge', '4']);
});

it('renders the view page without crashing when every optional field is empty', function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $leasingCompany = LeasingCompany::factory()->create([
        'contact_person' => null,
        'phone' => null,
        'email' => null,
        'notes' => null,
    ]);

    livewire(ViewLeasingCompany::class, ['record' => $leasingCompany->id])
        ->assertOk()
        ->assertSee('—');
});

describe('the edit button', function () {
    it('is hidden from a read-only user', function () {
        actingAs(User::factory()->viewer()->withTwoFactor()->create());

        $leasingCompany = LeasingCompany::factory()->create();

        livewire(ViewLeasingCompany::class, ['record' => $leasingCompany->id])
            ->assertActionHidden(EditAction::class);
    });

    it('leads an admin to the dedicated edit page, not a modal', function () {
        actingAs(User::factory()->admin()->withTwoFactor()->create());

        $leasingCompany = LeasingCompany::factory()->create();

        livewire(ViewLeasingCompany::class, ['record' => $leasingCompany->id])
            ->assertActionVisible(EditAction::class)
            ->assertActionHasUrl(EditAction::class, EditLeasingCompany::getUrl(['record' => $leasingCompany->id]));
    });
});
