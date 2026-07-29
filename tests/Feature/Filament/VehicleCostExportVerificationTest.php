<?php

use App\Filament\Exports\VehicleCostExporter;
use App\Filament\Resources\VehicleCosts\Pages\ListVehicleCosts;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

/**
 * Zusaetzliche, nicht in Task 10 geforderte Verifikation (nicht Teil der
 * Brief-Tests): sie beweist, dass der Kosten-Export tatsaechlich laeuft
 * (nicht nur, dass die Action existiert) und dass die exportierte Datei -
 * im Unterschied zum Fahrzeug-Export aus Task 9 - Kostenspalten enthaelt.
 */
it('actually runs the cost export under the sync queue and writes a file with cost columns', function () {
    config()->set('queue.default', 'sync');

    Storage::fake('local');

    actingAs(User::factory()->admin()->withCostAccess()->withTwoFactor()->create());

    Vehicle::factory()->count(3)->create([
        'monthly_lease_rate' => 100.00,
        'annual_vehicle_tax' => 50.00,
        'annual_insurance' => 25.00,
    ]);

    $columnMap = collect(VehicleCostExporter::getColumns())
        ->mapWithKeys(fn ($column) => [$column->getName() => ['isEnabled' => true, 'label' => $column->getLabel()]])
        ->all();

    livewire(ListVehicleCosts::class)
        ->callTableAction('export', data: ['columnMap' => $columnMap])
        ->assertHasNoTableActionErrors();

    $export = Export::latest('id')->first();

    expect($export)->not->toBeNull();
    expect($export->successful_rows)->toBe(3);
    expect($export->getFailedRowsCount())->toBe(0);

    $disk = Storage::disk($export->file_disk ?? 'local');
    $files = $disk->allFiles($export->getFileDirectory());

    $headersFile = collect($files)->first(fn ($f) => str_ends_with($f, 'headers.csv'));
    expect($headersFile)->not->toBeNull();

    // Filament schreibt die CSV mit einer UTF-8-BOM; die vor dem Parsen
    // entfernen, sonst haengt sie am ersten Feld ("\xEF\xBB\xBFKennzeichen").
    // Trennzeichen ist Semikolon (VehicleCostExporter::getCsvDelimiter()),
    // damit ein deutsches Excel die Datei tatsaechlich in Spalten zerlegt.
    $headerContent = ltrim($disk->get($headersFile), "\xEF\xBB\xBF");
    $headerRow = str_getcsv(trim($headerContent), ';');

    expect($headerRow)->toBe([
        'Kennzeichen',
        'Marke',
        'Modell',
        'Leasingrate monatlich',
        'Kfz-Steuer jaehrlich',
        'Versicherung jaehrlich',
        'Jahreskosten',
    ]);

    $dataFile = collect($files)->first(fn ($f) => preg_match('/\d+\.csv$/', $f));
    expect($dataFile)->not->toBeNull();

    $lines = explode("\n", trim($disk->get($dataFile)));
    expect($lines)->toHaveCount(3);

    // Spaltenreihenfolge exakt wie im Exporter (siehe Header-Assertion oben):
    // license_plate, brand, model, monthly_lease_rate, annual_vehicle_tax,
    // annual_insurance, annual_total_cost. Ein blosses toContain('100') auf
    // die gesamte Zeile waere unscharf, weil das Kennzeichen selbst Ziffern
    // enthaelt (z.B. "M-AB123") - daher hier auf die konkreten Spalten nach
    // Index, nicht auf die Zeile als Ganzes.
    foreach ($lines as $line) {
        $columns = str_getcsv($line, ';');

        // annual_total_cost wird ueber formatStateUsing() explizit auf zwei
        // Nachkommastellen formatiert (siehe VehicleCostExporter), genau wie
        // die drei decimal:2-Spalten davor - kein Formatunterschied mehr in
        // derselben Zeile.
        expect($columns[3])->toBe('100.00')
            ->and($columns[4])->toBe('50.00')
            ->and($columns[5])->toBe('25.00')
            ->and($columns[6])->toBe('1275.00');
    }
});

/**
 * Ergaenzung zum Test oben: dort gehen alle Betraege glatt auf (Endziffer 0),
 * was den fruehreren Fehler (float ohne Nachkommastellen in annual_total_cost)
 * verdeckt haette. Hier daher eine Leasingrate, die selbst zwei Nachkomma-
 * stellen ungleich Null hat, damit die Formatierung wirklich geprueft ist.
 */
it('keeps two decimal places for annual_total_cost even for uneven amounts', function () {
    config()->set('queue.default', 'sync');

    Storage::fake('local');

    actingAs(User::factory()->admin()->withCostAccess()->withTwoFactor()->create());

    Vehicle::factory()->create([
        'monthly_lease_rate' => 123.45,
        'annual_vehicle_tax' => 0,
        'annual_insurance' => 0,
    ]);

    $columnMap = collect(VehicleCostExporter::getColumns())
        ->mapWithKeys(fn ($column) => [$column->getName() => ['isEnabled' => true, 'label' => $column->getLabel()]])
        ->all();

    livewire(ListVehicleCosts::class)
        ->callTableAction('export', data: ['columnMap' => $columnMap])
        ->assertHasNoTableActionErrors();

    $export = Export::latest('id')->first();

    $disk = Storage::disk($export->file_disk ?? 'local');
    $files = $disk->allFiles($export->getFileDirectory());

    $dataFile = collect($files)->first(fn ($f) => preg_match('/\d+\.csv$/', $f));
    $line = trim($disk->get($dataFile));

    $columns = str_getcsv($line, ';');

    // 123.45 * 12 = 1481.40 - eine Zahl, die als float ("1481.4") ihre
    // trailing zero verlieren wuerde, waere die Formatierung nicht explizit.
    expect($columns[3])->toBe('123.45')
        ->and($columns[6])->toBe('1481.40');
});

/**
 * Der Export soll dem Status-Filter der Tabelle folgen: ein ausgemustertes
 * Fahrzeug mit einem auffaellig hohen Betrag darf nicht mitexportiert werden,
 * solange der Standardfilter (Active) aktiv ist. Sollte der Export tatsaechlich
 * ungefiltert exportieren, waere das ein Fund und keine Testkorrektur.
 */
it('follows the status filter and does not export retired vehicles with their costs', function () {
    config()->set('queue.default', 'sync');

    Storage::fake('local');

    actingAs(User::factory()->admin()->withCostAccess()->withTwoFactor()->create());

    Vehicle::factory()->create([
        'monthly_lease_rate' => 100.00,
        'annual_vehicle_tax' => 50.00,
        'annual_insurance' => 25.00,
    ]);

    Vehicle::factory()->retired()->create([
        'monthly_lease_rate' => 9000.00,
        'annual_vehicle_tax' => 9000.00,
        'annual_insurance' => 9000.00,
    ]);

    $columnMap = collect(VehicleCostExporter::getColumns())
        ->mapWithKeys(fn ($column) => [$column->getName() => ['isEnabled' => true, 'label' => $column->getLabel()]])
        ->all();

    // Kein assertTableFilterHasValue() verfuegbar (siehe Filament\Tables\Testing);
    // der Default (Active) ist bereits in "it shows active vehicles by default"
    // separat belegt. Hier zaehlt nur, dass der Export ihm folgt.
    livewire(ListVehicleCosts::class)
        ->callTableAction('export', data: ['columnMap' => $columnMap])
        ->assertHasNoTableActionErrors();

    $export = Export::latest('id')->first();

    expect($export)->not->toBeNull();
    expect($export->successful_rows)->toBe(1);
    expect($export->getFailedRowsCount())->toBe(0);

    $disk = Storage::disk($export->file_disk ?? 'local');
    $files = $disk->allFiles($export->getFileDirectory());

    $dataFile = collect($files)->first(fn ($f) => preg_match('/\d+\.csv$/', $f));
    expect($dataFile)->not->toBeNull();

    $lines = explode("\n", trim($disk->get($dataFile)));
    expect($lines)->toHaveCount(1);

    $columns = str_getcsv($lines[0], ';');
    expect($columns[3])->toBe('100.00');
});
