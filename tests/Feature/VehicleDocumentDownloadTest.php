<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

/**
 * Der Kern der Aufgabe: ein Fahrzeugschein enthaelt Fahrgestellnummer und
 * Halterdaten und darf unter keinen Umstaenden ohne Anmeldung abrufbar sein.
 * Diese Tests belegen das direkt am HTTP-Endpunkt, nicht nur an der
 * Sichtbarkeit einer Schaltflaeche.
 */
it('refuses an unauthenticated download and redirects to the login page', function () {
    Storage::fake('local');

    $document = VehicleDocument::factory()->for(Vehicle::factory())->create();
    Storage::disk('local')->put($document->file_path, 'dummy content');

    $response = $this->get(route('vehicle-documents.download', $document));

    $response->assertRedirect(route('login'));
    $response->assertStatus(302);
});

it('lets a read-only user download a document', function () {
    Storage::fake('local');

    $document = VehicleDocument::factory()->for(Vehicle::factory())->create([
        'original_filename' => 'fahrzeugschein.pdf',
    ]);
    Storage::disk('local')->put($document->file_path, 'dummy content');

    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $response = $this->get(route('vehicle-documents.download', $document));

    $response->assertOk();
    $response->assertHeader('content-disposition', 'attachment; filename=fahrzeugschein.pdf');
});

it('lets an admin download a document', function () {
    Storage::fake('local');

    $document = VehicleDocument::factory()->for(Vehicle::factory())->create();
    Storage::disk('local')->put($document->file_path, 'dummy content');

    actingAs(User::factory()->admin()->withTwoFactor()->create());

    $this->get(route('vehicle-documents.download', $document))->assertOk();
});

it('serves the original filename via content-disposition even though the stored path is random', function () {
    Storage::fake('local');

    $document = VehicleDocument::factory()->for(Vehicle::factory())->create([
        'file_path' => 'vehicle-documents/'.\Illuminate\Support\Str::ulid().'.pdf',
        'original_filename' => 'Übergabeprotokoll Muster.pdf',
    ]);
    Storage::disk('local')->put($document->file_path, 'dummy content');

    actingAs(User::factory()->viewer()->withTwoFactor()->create());

    $response = $this->get(route('vehicle-documents.download', $document));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('Muster.pdf');
});
