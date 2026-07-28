<?php

use App\Filament\Actions\GuardedDeleteAction;
use App\Filament\Resources\LeasingCompanies\Pages\CreateLeasingCompany;
use App\Filament\Resources\LeasingCompanies\Pages\EditLeasingCompany;
use App\Filament\Resources\LeasingCompanies\Pages\ListLeasingCompanies;
use App\Models\LeasingCompany;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
});

it('lists leasing companies', function () {
    $companies = LeasingCompany::factory()->count(3)->create();

    livewire(ListLeasingCompanies::class)
        ->assertOk()
        ->assertCanSeeTableRecords($companies);
});

it('creates a leasing company', function () {
    livewire(CreateLeasingCompany::class)
        ->fillForm([
            'name' => 'VW Leasing',
            'contact_person' => 'Herr Muster',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(LeasingCompany::class, ['name' => 'VW Leasing']);
});

it('requires a name', function () {
    livewire(CreateLeasingCompany::class)
        ->fillForm(['name' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('refuses to delete a company that is still referenced', function () {
    $company = LeasingCompany::factory()->create();
    Vehicle::factory()->for($company)->create(['license_plate' => 'M-EF 9012']);

    livewire(EditLeasingCompany::class, ['record' => $company->id])
        ->callAction(GuardedDeleteAction::class)
        ->assertNotified();

    assertDatabaseHas(LeasingCompany::class, ['id' => $company->id]);
});
