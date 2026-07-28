<?php

use App\Filament\Exports\VehicleExporter;
use App\Filament\Resources\Vehicles\Pages\ListVehicles;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

/**
 * Zusaetzliche, nicht in Task 9 geforderte Verifikation (nicht Teil der
 * Brief-Tests): sie beweist, dass der Export unter QUEUE_CONNECTION=sync
 * tatsaechlich funktioniert (nicht nur, dass die Action existiert) und dass
 * die exportierte Datei keine Kostenspalten enthaelt.
 */
it('actually runs the export under the sync queue and writes a file without cost columns', function () {
    config()->set('queue.default', 'sync');

    // Storage::fake() isoliert den Test von der echten Festplatte: ohne das
    // wuerden Dateien unter storage/app/.../filament_exports/ liegen bleiben,
    // und eine Praefix-Suche nach "\d+.csv" koennte einen Rest aus einem
    // frueheren Lauf treffen statt der Datei dieses Tests.
    Storage::fake('local');

    actingAs(User::factory()->admin()->withCostAccess()->withTwoFactor()->create());

    Vehicle::factory()->count(3)->create();

    $columnMap = collect(VehicleExporter::getColumns())
        ->mapWithKeys(fn ($column) => [$column->getName() => ['isEnabled' => true, 'label' => $column->getLabel()]])
        ->all();

    livewire(ListVehicles::class)
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
        ->toContain('Leasingende')
        ->not->toContain('Leasingrate')
        ->not->toContain('Kfz-Steuer')
        ->not->toContain('Versicherung');

    $dataFile = collect($files)->first(fn ($f) => preg_match('/\d+\.csv$/', $f));
    expect($dataFile)->not->toBeNull();

    $rows = trim($disk->get($dataFile));
    expect(substr_count($rows, "\n") + 1)->toBe(3);
});
