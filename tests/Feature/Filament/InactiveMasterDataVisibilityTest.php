<?php

use App\Filament\Resources\Dealerships\DealershipResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\FuelCardProviders\FuelCardProviderResource;
use App\Filament\Resources\FuelCards\FuelCardResource;
use App\Filament\Resources\LeasingCompanies\LeasingCompanyResource;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\Dealership;
use App\Models\Employee;
use App\Models\FuelCard;
use App\Models\FuelCardProvider;
use App\Models\LeasingCompany;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
});

describe('picker options in the vehicle form', function () {
    it('does not offer an inactive employee as a driver', function () {
        $active = Employee::factory()->create(['active' => true]);
        $inactive = Employee::factory()->create(['active' => false]);

        $vehicle = Vehicle::factory()->create(['employee_id' => null]);

        $options = livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
            ->instance()
            ->getSchemaComponent('form.employee_id')
            ->getOptions();

        expect($options)->toHaveKey($active->id)
            ->and($options)->not->toHaveKey($inactive->id);
    });

    // Der Fallstrick: Waere der bereits gesetzte, inzwischen inaktive Fahrer
    // nicht mehr in der Optionsliste, faende das Formular ihn nicht wieder
    // und wuerde die Zuordnung beim naechsten Speichern still leeren.
    it('keeps an already assigned employee selectable after they were deactivated', function () {
        $employee = Employee::factory()->create(['active' => true]);
        $vehicle = Vehicle::factory()->for($employee)->create();

        $employee->update(['active' => false]);

        $options = livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
            ->instance()
            ->getSchemaComponent('form.employee_id')
            ->getOptions();

        expect($options)->toHaveKey($employee->id);
    });

    it('does not save away an inactive assignment when the form is submitted', function () {
        $employee = Employee::factory()->create(['active' => true]);
        $vehicle = Vehicle::factory()->for($employee)->create();

        $employee->update(['active' => false]);

        livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($vehicle->fresh()->employee_id)->toBe($employee->id);
    });

    it('hides inactive dealerships, leasing companies and fuel cards from their pickers', function () {
        $vehicle = Vehicle::factory()->create();
        $inactiveDealer = Dealership::factory()->create(['active' => false]);
        $inactiveLessor = LeasingCompany::factory()->create(['active' => false]);
        $provider = FuelCardProvider::factory()->create(['active' => true]);
        $inactiveCard = FuelCard::factory()->create(['fuel_card_provider_id' => $provider->id, 'active' => false]);

        $form = livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])->instance();

        expect($form->getSchemaComponent('form.dealership_id')->getOptions())->not->toHaveKey($inactiveDealer->id)
            ->and($form->getSchemaComponent('form.leasing_company_id')->getOptions())->not->toHaveKey($inactiveLessor->id)
            ->and($form->getSchemaComponent('form.fuel_card_id')->getOptions())->not->toHaveKey($inactiveCard->id);
    });
});

describe('global search', function () {
    it('excludes an inactive employee', function () {
        Employee::factory()->create(['active' => false, 'last_name' => 'Unsichtbar']);

        $results = EmployeeResource::getGlobalSearchEloquentQuery()->pluck('last_name');

        expect($results)->not->toContain('Unsichtbar');
    });

    it('excludes inactive dealerships, leasing companies, cards and providers', function () {
        Dealership::factory()->create(['active' => false, 'name' => 'Autohaus X']);
        LeasingCompany::factory()->create(['active' => false, 'name' => 'Leasing Y']);
        FuelCardProvider::factory()->create(['active' => false, 'name' => 'Anbieter Z']);
        $inactiveProvider = FuelCardProvider::factory()->create(['active' => true]);
        FuelCard::factory()->create(['fuel_card_provider_id' => $inactiveProvider->id, 'active' => false]);

        expect(DealershipResource::getGlobalSearchEloquentQuery()->pluck('name'))->not->toContain('Autohaus X');
        expect(LeasingCompanyResource::getGlobalSearchEloquentQuery()->pluck('name'))->not->toContain('Leasing Y');
        expect(FuelCardProviderResource::getGlobalSearchEloquentQuery()->pluck('name'))->not->toContain('Anbieter Z');
        expect(FuelCardResource::getGlobalSearchEloquentQuery()->count())->toBe(0);
    });

    it('excludes a retired vehicle', function () {
        Vehicle::factory()->retired()->create(['license_plate' => 'M-RE 999']);

        $plates = VehicleResource::getGlobalSearchEloquentQuery()->pluck('license_plate');

        expect($plates)->not->toContain('M-RE 999');
    });
});

describe('display of inactive references', function () {
    // Wird der Fahrer inzwischen inaktiv, ist er trotzdem noch der Fahrer
    // dieses Fahrzeugs. Die Anzeige soll das nicht verschweigen und den
    // Zustand nennen - sonst wirkt der Datensatz plaetzlich, als sei nie
    // jemand eingetragen gewesen.
    it('marks an inactive driver in the vehicle list with a suffix', function () {
        $employee = Employee::factory()->create(['active' => false, 'first_name' => 'Max', 'last_name' => 'Muster']);
        Vehicle::factory()->for($employee)->create();

        livewire(ListVehicles::class)
            ->assertSee('Max Muster (inaktiv)');
    });
});
