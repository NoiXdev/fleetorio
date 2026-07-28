<?php

use App\Enums\FuelCardProvider;
use App\Models\Employee;
use App\Models\FuelCard;
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

it('casts the fuel card provider to an enum', function () {
    $card = FuelCard::factory()->create(['provider' => FuelCardProvider::Dkv]);

    expect($card->fresh()->provider)->toBe(FuelCardProvider::Dkv);
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
