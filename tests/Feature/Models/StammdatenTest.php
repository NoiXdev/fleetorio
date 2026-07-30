<?php

use App\Models\Employee;
use App\Models\FuelCard;
use App\Models\FuelCardProvider;
use App\Models\LeasingCompany;

it('composes an employee full name', function () {
    $employee = Employee::factory()->create([
        'first_name' => 'Anna',
        'last_name' => 'Beispiel',
    ]);

    expect($employee->full_name)->toBe('Anna Beispiel');
});

it('defaults employees to active', function () {
    expect(Employee::factory()->create()->active)->toBeTrue();
});

it('assigns a fuel card to a maintainable provider', function () {
    $provider = FuelCardProvider::factory()->create(['name' => 'Testanbieter']);
    $card = FuelCard::factory()->create(['fuel_card_provider_id' => $provider->id]);

    expect($card->fresh()->fuelCardProvider->name)->toBe('Testanbieter');
});

it('seeds sensible starting values for fuel card providers via the migration', function () {
    // Ersetzt den fruehreren Test "casts the fuel card provider to an enum":
    // ein Enum-Wert laesst sich nach der Migration
    // 2026_07_30_180001_replace_fuel_card_provider_enum_with_relation nicht
    // mehr anlegen (die Spalte fuel_cards.provider existiert nicht mehr).
    // Stattdessen wird hier geprueft, dass eine frische Installation nicht
    // mit einer leeren Anbieterliste beginnt und dass sich eine Karte einem
    // dieser Startwerte zuordnen laesst.
    $names = FuelCardProvider::pluck('name');

    expect($names)->toContain('DKV')
        ->and($names)->toContain('Elli')
        ->and($names)->toContain('Sonstige')
        ->and($names->count())->toBeGreaterThanOrEqual(5);

    $dkv = FuelCardProvider::where('name', 'DKV')->first();
    $card = FuelCard::factory()->create(['fuel_card_provider_id' => $dkv->id]);

    expect($card->fresh()->fuelCardProvider->name)->toBe('DKV');
});

it('encrypts the fuel card number in the database', function () {
    $card = FuelCard::factory()->create(['card_number' => '7080123456789']);

    // Über das Model gelesen: Klartext.
    expect($card->fresh()->card_number)->toBe('7080123456789');

    // Direkt aus der Datenbank gelesen: verschlüsselt.
    $raw = DB::table('fuel_cards')->where('id', $card->id)->value('card_number');
    expect($raw)->not->toBe('7080123456789')
        ->and($raw)->not->toContain('7080');
});

it('casts the fuel card validity to a date', function () {
    $card = FuelCard::factory()->create(['valid_until' => '2028-12-31']);

    expect($card->fresh()->valid_until->format('Y-m-d'))->toBe('2028-12-31');
});

it('stores a leasing company with contact details', function () {
    $company = LeasingCompany::factory()->create([
        'name' => 'VW Leasing',
        'contact_person' => 'Herr Muster',
    ]);

    expect($company->fresh()->name)->toBe('VW Leasing')
        ->and($company->fresh()->contact_person)->toBe('Herr Muster');
});
