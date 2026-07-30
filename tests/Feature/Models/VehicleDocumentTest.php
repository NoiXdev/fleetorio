<?php

use App\Enums\DocumentType;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Support\Facades\Storage;

it('casts the type to an enum', function () {
    $document = VehicleDocument::factory()
        ->for(Vehicle::factory())
        ->create(['type' => DocumentType::Registration]);

    expect($document->fresh()->type)->toBe(DocumentType::Registration);
});

it('belongs to a vehicle', function () {
    $vehicle = Vehicle::factory()->create();
    $document = VehicleDocument::factory()->for($vehicle)->create();

    expect($document->vehicle->is($vehicle))->toBeTrue();
});

describe('formatted file size', function () {
    it('formats bytes', function () {
        $document = VehicleDocument::factory()->for(Vehicle::factory())->create(['file_size' => 512]);

        expect($document->formattedFileSize())->toBe('512 B');
    });

    it('formats kilobytes', function () {
        $document = VehicleDocument::factory()->for(Vehicle::factory())->create(['file_size' => 4096]);

        expect($document->formattedFileSize())->toBe('4,0 KB');
    });

    it('formats megabytes with a german decimal comma', function () {
        // 2,4 MB, wie im Auftrag als Beispiel genannt.
        $document = VehicleDocument::factory()->for(Vehicle::factory())->create([
            'file_size' => (int) round(2.4 * 1024 * 1024),
        ]);

        expect($document->formattedFileSize())->toBe('2,4 MB');
    });
});

it('deletes the stored file when the document is deleted', function () {
    Storage::fake('local');

    $path = 'vehicle-documents/test-file.pdf';
    Storage::disk('local')->put($path, 'dummy content');

    $document = VehicleDocument::factory()->for(Vehicle::factory())->create(['file_path' => $path]);

    expect(Storage::disk('local')->exists($path))->toBeTrue();

    $document->delete();

    expect(Storage::disk('local')->exists($path))->toBeFalse();
    expect(VehicleDocument::count())->toBe(0);
});
