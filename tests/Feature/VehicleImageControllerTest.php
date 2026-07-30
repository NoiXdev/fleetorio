<?php

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

/**
 * Der Kern der Aufgabe: ein Fahrzeugfoto zeigt in aller Regel das Kennzeichen
 * und darf deshalb unter keinen Umstaenden ohne Anmeldung abrufbar sein.
 * Diese Tests belegen das direkt am HTTP-Endpunkt, nicht nur an der
 * Sichtbarkeit einer Schaltflaeche - dasselbe Muster wie
 * VehicleDocumentDownloadTest.
 */
it('refuses an unauthenticated request and redirects to the login page', function () {
    Storage::fake('local');

    $vehicle = Vehicle::factory()->withImage()->create();
    Storage::disk('local')->put($vehicle->image_path, 'dummy image content');

    $response = $this->get(route('vehicle-images.show', $vehicle));

    $response->assertRedirect(route('login'));
    $response->assertStatus(302);
});

it('lets a read-only user see the image', function () {
    Storage::fake('local');

    $vehicle = Vehicle::factory()->withImage()->create();
    Storage::disk('local')->put($vehicle->image_path, 'dummy image content');

    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $response = $this->get(route('vehicle-images.show', $vehicle));

    $response->assertOk();
});

it('lets an admin see the image', function () {
    Storage::fake('local');

    $vehicle = Vehicle::factory()->withImage()->create();
    Storage::disk('local')->put($vehicle->image_path, 'dummy image content');

    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $this->get(route('vehicle-images.show', $vehicle))->assertOk();
});

it('serves the image inline, not as a file download', function () {
    // Anders als beim Dokument-Download: das Bild soll in <img>-Tags
    // erscheinen, nicht als Datei mit Speichern-Dialog heruntergeladen
    // werden - siehe Storage::response() (Standard-Disposition 'inline')
    // statt Storage::download() im Controller.
    Storage::fake('local');

    $vehicle = Vehicle::factory()->withImage()->create();
    Storage::disk('local')->put($vehicle->image_path, 'dummy image content');

    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $response = $this->get(route('vehicle-images.show', $vehicle));

    expect($response->headers->get('content-disposition'))->toContain('inline');
});

it('returns 404 when the vehicle has no image', function () {
    Storage::fake('local');

    $vehicle = Vehicle::factory()->create(['image_path' => null]);

    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $this->get(route('vehicle-images.show', $vehicle))->assertNotFound();
});
