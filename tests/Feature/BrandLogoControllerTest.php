<?php

use App\Models\AppSetting;
use Illuminate\Support\Facades\Storage;

/**
 * Das Logo erscheint auf der Anmeldeseite und als Favicon, beides ohne
 * Session erreichbar - im Unterschied zu VehicleImageControllerTest und
 * VehicleDocumentDownloadTest, wo genau das Gegenteil belegt wird.
 */
it('serves the configured logo without requiring authentication', function () {
    Storage::fake('local');
    Storage::disk('local')->put('branding/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    AppSetting::factory()->create(['logo_path' => 'branding/logo.svg']);

    $this->get(route('branding.logo'))->assertOk();
});

it('returns 404 when no custom logo is configured', function () {
    Storage::fake('local');

    $this->get(route('branding.logo'))->assertNotFound();
});

it('returns 404 when the configured logo file is missing from disk', function () {
    Storage::fake('local');

    AppSetting::factory()->create(['logo_path' => 'branding/does-not-exist.svg']);

    $this->get(route('branding.logo'))->assertNotFound();
});

it('sets caching headers so the logo is not refetched on every page load', function () {
    Storage::fake('local');
    Storage::disk('local')->put('branding/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    AppSetting::factory()->create(['logo_path' => 'branding/logo.svg']);

    $response = $this->get(route('branding.logo'));

    expect($response->headers->get('Cache-Control'))->toContain('max-age')
        ->and($response->headers->get('Last-Modified'))->not->toBeNull();
});

it('sets a content security policy header to neutralise an embedded script on direct navigation', function () {
    // Zweite, unabhaengige Verteidigungslinie neben der Inhaltspruefung beim
    // Upload (siehe App\Rules\RejectsUnsafeSvg) - greift auch dann, wenn
    // eine hochgeladene Datei diese Pruefung dennoch durchrutschen sollte.
    Storage::fake('local');
    Storage::disk('local')->put('branding/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    AppSetting::factory()->create(['logo_path' => 'branding/logo.svg']);

    $response = $this->get(route('branding.logo'));

    expect($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox');
});
