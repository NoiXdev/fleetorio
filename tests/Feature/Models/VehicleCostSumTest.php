<?php

use App\Models\Vehicle;

it('sums annual costs across a query', function () {
    Vehicle::factory()->create([
        'monthly_lease_rate' => 100.00,
        'annual_vehicle_tax' => 50.00,
        'annual_insurance' => 25.00,
    ]);
    Vehicle::factory()->create([
        'monthly_lease_rate' => 200.00,
        'annual_vehicle_tax' => 10.00,
        'annual_insurance' => 5.00,
    ]);

    // (100*12 + 50 + 25) + (200*12 + 10 + 5) = 1275 + 2415 = 3690
    expect(Vehicle::sumAnnualCost(Vehicle::query()))->toBe(3690.0);
});

it('treats missing values as zero when summing', function () {
    Vehicle::factory()->create([
        'monthly_lease_rate' => 100.00,
        'annual_vehicle_tax' => null,
        'annual_insurance' => null,
    ]);

    expect(Vehicle::sumAnnualCost(Vehicle::query()))->toBe(1200.0);
});

it('sums only what the query selects', function () {
    Vehicle::factory()->create(['monthly_lease_rate' => 100.00, 'annual_vehicle_tax' => null, 'annual_insurance' => null]);
    Vehicle::factory()->retired()->create(['monthly_lease_rate' => 999.00, 'annual_vehicle_tax' => null, 'annual_insurance' => null]);

    expect(Vehicle::sumAnnualCost(Vehicle::active()))->toBe(1200.0);
});

it('returns zero for an empty query', function () {
    expect(Vehicle::sumAnnualCost(Vehicle::query()))->toBe(0.0);
});
