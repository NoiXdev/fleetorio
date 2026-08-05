<?php

use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->withTwoFactor()->create());
    Storage::fake('local');
});

// Der Kern des Fehlers: imageAspectRatio('4:3') ist nicht nur eine Vorgabe
// fuer den Zuschneide-Editor im Browser, sondern erzeugt serverseitig
// Rule::dimensions()->ratio(). Ein Bild in einem anderen Verhaeltnis wurde
// dadurch abgewiesen, obwohl die Bildgroesse laut Anforderung egal sein soll.
it('accepts an image that is not in a 4:3 ratio', function () {
    $vehicle = Vehicle::factory()->create(['image_path' => null]);

    livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->fillForm(['image_path' => [UploadedFile::fake()->image('panorama.jpg', 1600, 400)]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($vehicle->fresh()->image_path)->not->toBeNull();
});

it('accepts an image far below the resize width of 2000 pixels', function () {
    $vehicle = Vehicle::factory()->create(['image_path' => null]);

    livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->fillForm(['image_path' => [UploadedFile::fake()->image('klein.jpg', 800, 600)]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($vehicle->fresh()->image_path)->not->toBeNull();
});

// Die einzige Grenze, die bleiben soll.
it('still rejects an image above the ten megabyte limit', function () {
    $vehicle = Vehicle::factory()->create(['image_path' => null]);

    livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->fillForm([
            'image_path' => [UploadedFile::fake()->image('gross.jpg', 4000, 3000)->size(11 * 1024)],
        ])
        ->call('save')
        ->assertHasFormErrors(['image_path']);
});

// Die zweite Haelfte des Fehlers - das Hochrechnen kleiner Bilder auf 2000
// Pixel - passiert im Browser, in FilePondPluginImageResize. Ein Test im
// PHP-Prozess durchlaeuft diesen Schritt nie und kann das Ergebnis deshalb
// nicht pruefen. Was er pruefen kann: dass die Anweisung, es zu unterlassen,
// ueberhaupt bis zur Komponente gelangt und nicht bei einer spaeteren
// Aenderung still verlorengeht.
it('tells the browser not to upscale and not to crop', function () {
    $vehicle = Vehicle::factory()->create();

    $upload = livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->instance()
        ->getSchemaComponent('form.image_path');

    expect($upload->shouldAutomaticallyUpscaleImagesWhenResizing())->toBeFalse()
        ->and($upload->getAutomaticallyResizeImagesMode())->toBe('contain')
        ->and($upload->getAutomaticallyResizeImagesWidth())->toBe('2000');
});

it('still rejects a file type that is not an image', function () {
    $vehicle = Vehicle::factory()->create(['image_path' => null]);

    livewire(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
        ->fillForm(['image_path' => [UploadedFile::fake()->create('handbuch.pdf', 100, 'application/pdf')]])
        ->call('save')
        ->assertHasFormErrors(['image_path']);
});
