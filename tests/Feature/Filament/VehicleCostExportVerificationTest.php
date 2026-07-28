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

    $header = $disk->get($headersFile);

    expect($header)->toContain('Kennzeichen')
        ->toContain('Marke')
        ->toContain('Leasingrate monatlich')
        ->toContain('Kfz-Steuer jaehrlich')
        ->toContain('Versicherung jaehrlich')
        ->toContain('Jahreskosten');

    $dataFile = collect($files)->first(fn ($f) => preg_match('/\d+\.csv$/', $f));
    expect($dataFile)->not->toBeNull();

    $rows = trim($disk->get($dataFile));
    expect($rows)->toContain('100')
        ->toContain('50')
        ->toContain('25');
    expect(substr_count($rows, "\n") + 1)->toBe(3);
});
