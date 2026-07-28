<?php

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use App\Models\Employee;
use App\Models\FuelCard;
use App\Models\LeasingCompany;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;

it('sums annual total cost from lease rate, tax and insurance', function () {
    $vehicle = Vehicle::factory()->create([
        'monthly_lease_rate' => 450.00,
        'annual_vehicle_tax' => 180.00,
        'annual_insurance' => 620.00,
    ]);

    // 450 * 12 + 180 + 620 = 6200
    expect($vehicle->annual_total_cost)->toBe(6200.0);
});

it('treats missing cost values as zero', function () {
    $vehicle = Vehicle::factory()->create([
        'monthly_lease_rate' => 300.00,
        'annual_vehicle_tax' => null,
        'annual_insurance' => null,
    ]);

    expect($vehicle->annual_total_cost)->toBe(3600.0);
});

it('reports zero annual cost when nothing is known', function () {
    $vehicle = Vehicle::factory()->create([
        'monthly_lease_rate' => null,
        'annual_vehicle_tax' => null,
        'annual_insurance' => null,
    ]);

    expect($vehicle->annual_total_cost)->toBe(0.0);
});

it('casts fuel type and status to enums', function () {
    $vehicle = Vehicle::factory()->create(['fuel_type' => FuelType::Electric]);

    expect($vehicle->fresh()->fuel_type)->toBe(FuelType::Electric)
        ->and($vehicle->fresh()->status)->toBe(VehicleStatus::Active);
});

it('defaults new vehicles to active', function () {
    expect(Vehicle::factory()->create()->status)->toBe(VehicleStatus::Active);
});

it('scopes to active vehicles only', function () {
    Vehicle::factory()->count(2)->create();
    Vehicle::factory()->retired()->create();

    expect(Vehicle::active()->count())->toBe(2);
});

describe('lease expiry window', function () {
    it('includes a lease ending today', function () {
        Vehicle::factory()->create(['lease_end' => today()]);

        expect(Vehicle::leaseEndingWithin(180)->count())->toBe(1);
    });

    it('includes a lease ending exactly at the window edge', function () {
        Vehicle::factory()->create(['lease_end' => today()->addDays(180)]);

        expect(Vehicle::leaseEndingWithin(180)->count())->toBe(1);
    });

    it('excludes a lease ending one day beyond the window', function () {
        Vehicle::factory()->create(['lease_end' => today()->addDays(181)]);

        expect(Vehicle::leaseEndingWithin(180)->count())->toBe(0);
    });

    it('excludes leases that already expired', function () {
        Vehicle::factory()->create(['lease_end' => today()->subDay()]);

        expect(Vehicle::leaseEndingWithin(180)->count())->toBe(0);
    });

    it('excludes retired vehicles even inside the window', function () {
        Vehicle::factory()->retired()->create(['lease_end' => today()->addDays(30)]);

        expect(Vehicle::leaseEndingWithin(180)->count())->toBe(0);
    });
});

it('uses a 180 day warning window', function () {
    expect(Vehicle::LEASE_WARNING_DAYS)->toBe(180);
});

it('relates to employee, fuel card and leasing company', function () {
    $vehicle = Vehicle::factory()
        ->for(Employee::factory())
        ->for(FuelCard::factory())
        ->for(LeasingCompany::factory())
        ->create();

    expect($vehicle->employee)->toBeInstanceOf(Employee::class)
        ->and($vehicle->fuelCard)->toBeInstanceOf(FuelCard::class)
        ->and($vehicle->leasingCompany)->toBeInstanceOf(LeasingCompany::class);
});

it('allows a vehicle without employee, card or leasing company', function () {
    $vehicle = Vehicle::factory()->create([
        'employee_id' => null,
        'fuel_card_id' => null,
        'leasing_company_id' => null,
    ]);

    expect($vehicle->employee)->toBeNull()
        ->and($vehicle->fuelCard)->toBeNull()
        ->and($vehicle->leasingCompany)->toBeNull();
});

describe('referential integrity', function () {
    it('refuses to delete an employee that still has a vehicle', function () {
        $employee = Employee::factory()->create();
        Vehicle::factory()->for($employee)->create();

        expect(fn () => $employee->delete())->toThrow(QueryException::class);
    });

    it('refuses to delete a fuel card that is still assigned', function () {
        $card = FuelCard::factory()->create();
        Vehicle::factory()->for($card)->create();

        expect(fn () => $card->delete())->toThrow(QueryException::class);
    });

    it('refuses to delete a leasing company that is still referenced', function () {
        $company = LeasingCompany::factory()->create();
        Vehicle::factory()->for($company)->create();

        expect(fn () => $company->delete())->toThrow(QueryException::class);
    });

    it('protects assignments of retired vehicles too', function () {
        $employee = Employee::factory()->create();
        Vehicle::factory()->retired()->for($employee)->create();

        expect(fn () => $employee->delete())->toThrow(QueryException::class);
    });

    it('allows deleting an employee without vehicles', function () {
        $employee = Employee::factory()->create();

        $employee->delete();

        expect(Employee::count())->toBe(0);
    });
});

it('exposes vehicles from the master data side', function () {
    $employee = Employee::factory()->create();
    Vehicle::factory()->count(2)->for($employee)->create();

    expect($employee->vehicles)->toHaveCount(2);
});
