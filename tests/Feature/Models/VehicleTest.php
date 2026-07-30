<?php

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use App\Models\Dealership;
use App\Models\Employee;
use App\Models\FuelCard;
use App\Models\LeasingCompany;
use App\Models\Vehicle;
use App\Models\VehicleAppointment;
use App\Models\VehicleDocument;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;

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

    it('excludes a vehicle without a lease end at all', function () {
        // whereBetween() schliesst NULL automatisch aus (SQL: NULL BETWEEN
        // x AND y ist NULL, also falsy) - hier verifiziert statt angenommen.
        Vehicle::factory()->withoutLeaseDates()->create();

        expect(Vehicle::leaseEndingWithin(180)->count())->toBe(0);
    });
});

it('uses a 180 day warning window', function () {
    expect(Vehicle::LEASE_WARNING_DAYS)->toBe(180);
});

describe('lease urgency color', function () {
    it('marks an already expired lease as gray', function () {
        expect(Vehicle::leaseUrgencyColor(-1))->toBe('gray');
    });

    it('marks exactly the urgent threshold as danger', function () {
        expect(Vehicle::leaseUrgencyColor(Vehicle::LEASE_URGENT_DAYS))->toBe('danger');
    });

    it('marks one day past the urgent threshold as warning', function () {
        expect(Vehicle::leaseUrgencyColor(Vehicle::LEASE_URGENT_DAYS + 1))->toBe('warning');
    });

    it('marks exactly the warning threshold as warning', function () {
        expect(Vehicle::leaseUrgencyColor(Vehicle::LEASE_WARNING_DAYS))->toBe('warning');
    });

    it('marks one day past the warning threshold as success', function () {
        expect(Vehicle::leaseUrgencyColor(Vehicle::LEASE_WARNING_DAYS + 1))->toBe('success');
    });
});

describe('inspection due window', function () {
    it('includes an inspection due today', function () {
        Vehicle::factory()->create(['inspection_valid_until' => today()]);

        expect(Vehicle::inspectionDueWithin(60)->count())->toBe(1);
    });

    it('includes an inspection due exactly at the window edge', function () {
        Vehicle::factory()->create(['inspection_valid_until' => today()->addDays(60)]);

        expect(Vehicle::inspectionDueWithin(60)->count())->toBe(1);
    });

    it('excludes an inspection due one day beyond the window', function () {
        Vehicle::factory()->create(['inspection_valid_until' => today()->addDays(61)]);

        expect(Vehicle::inspectionDueWithin(60)->count())->toBe(0);
    });

    it('excludes an inspection that already expired', function () {
        Vehicle::factory()->create(['inspection_valid_until' => today()->subDay()]);

        expect(Vehicle::inspectionDueWithin(60)->count())->toBe(0);
    });

    it('excludes retired vehicles even inside the window', function () {
        Vehicle::factory()->retired()->create(['inspection_valid_until' => today()->addDays(10)]);

        expect(Vehicle::inspectionDueWithin(60)->count())->toBe(0);
    });

    it('excludes a vehicle without an inspection date at all', function () {
        // whereBetween() schliesst NULL automatisch aus (SQL: NULL BETWEEN
        // x AND y ist NULL, also falsy).
        Vehicle::factory()->create(['inspection_valid_until' => null]);

        expect(Vehicle::inspectionDueWithin(60)->count())->toBe(0);
    });
});

it('uses a 60 day inspection warning window', function () {
    expect(Vehicle::INSPECTION_WARNING_DAYS)->toBe(60);
});

describe('inspection urgency color', function () {
    it('marks an already expired inspection as gray', function () {
        expect(Vehicle::inspectionUrgencyColor(-1))->toBe('gray');
    });

    it('marks exactly the urgent threshold as danger', function () {
        expect(Vehicle::inspectionUrgencyColor(Vehicle::INSPECTION_URGENT_DAYS))->toBe('danger');
    });

    it('marks one day past the urgent threshold as warning', function () {
        expect(Vehicle::inspectionUrgencyColor(Vehicle::INSPECTION_URGENT_DAYS + 1))->toBe('warning');
    });

    it('marks exactly the warning threshold as warning', function () {
        expect(Vehicle::inspectionUrgencyColor(Vehicle::INSPECTION_WARNING_DAYS))->toBe('warning');
    });

    it('marks one day past the warning threshold as success', function () {
        expect(Vehicle::inspectionUrgencyColor(Vehicle::INSPECTION_WARNING_DAYS + 1))->toBe('success');
    });
});

it('relates to employee, fuel card, leasing company and dealership', function () {
    $vehicle = Vehicle::factory()
        ->for(Employee::factory())
        ->for(FuelCard::factory())
        ->for(LeasingCompany::factory())
        ->for(Dealership::factory())
        ->create();

    expect($vehicle->employee)->toBeInstanceOf(Employee::class)
        ->and($vehicle->fuelCard)->toBeInstanceOf(FuelCard::class)
        ->and($vehicle->leasingCompany)->toBeInstanceOf(LeasingCompany::class)
        ->and($vehicle->dealership)->toBeInstanceOf(Dealership::class);
});

it('allows a vehicle without employee, card, leasing company or dealership', function () {
    $vehicle = Vehicle::factory()->create([
        'employee_id' => null,
        'fuel_card_id' => null,
        'leasing_company_id' => null,
        'dealership_id' => null,
    ]);

    expect($vehicle->employee)->toBeNull()
        ->and($vehicle->fuelCard)->toBeNull()
        ->and($vehicle->leasingCompany)->toBeNull()
        ->and($vehicle->dealership)->toBeNull();
});

it('keeps the leasing company unaffected by a dealership change', function () {
    // Auftraggeber-Entscheidung: Autohaus und Leasinggeber sind getrennt
    // gefuehrt - ein Autohauswechsel darf den Leasingvertrag nicht beruehren.
    $leasingCompany = LeasingCompany::factory()->create();
    $oldDealership = Dealership::factory()->create();
    $newDealership = Dealership::factory()->create();

    $vehicle = Vehicle::factory()->for($leasingCompany)->for($oldDealership)->create();

    $vehicle->update(['dealership_id' => $newDealership->id]);

    expect($vehicle->fresh()->dealership->is($newDealership))->toBeTrue()
        ->and($vehicle->fresh()->leasingCompany->is($leasingCompany))->toBeTrue();
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

    it('refuses to delete a dealership that is still referenced', function () {
        $dealership = Dealership::factory()->create();
        Vehicle::factory()->for($dealership)->create();

        expect(fn () => $dealership->delete())->toThrow(QueryException::class);
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

it('orders appointments by date descending so the newest one is on top', function () {
    $vehicle = Vehicle::factory()->create();
    $oldest = VehicleAppointment::factory()->for($vehicle)->create(['date' => today()->subYear()]);
    $newest = VehicleAppointment::factory()->for($vehicle)->create(['date' => today()]);
    $middle = VehicleAppointment::factory()->for($vehicle)->create(['date' => today()->subMonths(3)]);

    expect($vehicle->appointments->pluck('id')->all())
        ->toBe([$newest->id, $middle->id, $oldest->id]);
});

it('deletes a vehicle\'s appointments when the vehicle itself is deleted', function () {
    // cascadeOnDelete statt restrictOnDelete wie bei den Stammdaten: Termine
    // gehoeren zum Fahrzeug und haben ohne es keinen Sinn.
    $vehicle = Vehicle::factory()->create();
    VehicleAppointment::factory()->for($vehicle)->count(2)->create();

    $vehicle->delete();

    expect(VehicleAppointment::count())->toBe(0);
});

describe('vehicle image', function () {
    it('deletes the image file when the vehicle itself is deleted', function () {
        Storage::fake('local');

        $vehicle = Vehicle::factory()->withImage()->create();
        Storage::disk('local')->put($vehicle->image_path, 'dummy image content');

        $path = $vehicle->image_path;

        $vehicle->delete();

        expect(Storage::disk('local')->exists($path))->toBeFalse();
    });

    it('does nothing on delete when no image was ever set', function () {
        Storage::fake('local');

        $vehicle = Vehicle::factory()->create(['image_path' => null]);

        // Darf nicht crashen (kein delete() auf einen null-Pfad).
        $vehicle->delete();

        expect(Vehicle::count())->toBe(0);
    });

    it('deletes the old file when the image is replaced', function () {
        Storage::fake('local');

        $vehicle = Vehicle::factory()->withImage()->create();
        $oldPath = $vehicle->image_path;
        Storage::disk('local')->put($oldPath, 'old image content');

        $newPath = 'vehicle-images/new-image.jpg';
        Storage::disk('local')->put($newPath, 'new image content');

        $vehicle->update(['image_path' => $newPath]);

        expect(Storage::disk('local')->exists($oldPath))->toBeFalse()
            ->and(Storage::disk('local')->exists($newPath))->toBeTrue()
            ->and($vehicle->fresh()->image_path)->toBe($newPath);
    });

    it('deletes the old file when the image is removed entirely', function () {
        Storage::fake('local');

        $vehicle = Vehicle::factory()->withImage()->create();
        $oldPath = $vehicle->image_path;
        Storage::disk('local')->put($oldPath, 'old image content');

        $vehicle->update(['image_path' => null]);

        expect(Storage::disk('local')->exists($oldPath))->toBeFalse()
            ->and($vehicle->fresh()->image_path)->toBeNull();
    });

    it('does not touch the file when saving without changing the image', function () {
        Storage::fake('local');

        $vehicle = Vehicle::factory()->withImage()->create();
        Storage::disk('local')->put($vehicle->image_path, 'image content');

        $vehicle->update(['brand' => 'Audi']);

        expect(Storage::disk('local')->exists($vehicle->image_path))->toBeTrue();
    });
});

it('deletes a vehicle\'s documents and their files when the vehicle itself is deleted', function () {
    // Anders als bei Terminen reicht die Datenbank-Kaskade (cascadeOnDelete
    // auf der Migration) hier nicht: sie raeumt nur die Zeilen ab, nicht die
    // Dateien auf dem privaten Disk (das ist ein reiner DB-Vorgang und loest
    // VehicleDocument::deleting() nicht aus). Vehicle::booted() loescht die
    // Dokumente deshalb explizit ueber Eloquent, siehe Kommentar dort.
    Storage::fake('local');

    $vehicle = Vehicle::factory()->create();
    $documents = VehicleDocument::factory()->for($vehicle)->count(2)->create();

    foreach ($documents as $document) {
        Storage::disk('local')->put($document->file_path, 'dummy content');
    }

    $vehicle->delete();

    expect(VehicleDocument::count())->toBe(0);

    foreach ($documents as $document) {
        expect(Storage::disk('local')->exists($document->file_path))->toBeFalse();
    }
});
