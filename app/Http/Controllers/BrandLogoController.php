<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Liefert ein hochgeladenes Firmenlogo aus - im Unterschied zu
 * VehicleImageController und VehicleDocumentDownloadController bewusst OHNE
 * Anmeldepflicht: das Logo erscheint auf der Anmeldeseite selbst und als
 * Favicon, beides Kontexte ganz ohne Session. Trotzdem kein
 * public/storage-Symlink (siehe CLAUDE.md) - das Logo liegt auf dem privaten
 * Disk 'local' und wird ausschliesslich ueber diese Route ausgeliefert.
 *
 * Nur fuer ein hochgeladenes Logo zustaendig: das Standardlogo liegt bereits
 * oeffentlich unter public/images (siehe AppSetting::DEFAULT_LOGO_ASSET_PATH)
 * und wird direkt vom Webserver ausgeliefert, ohne ueber PHP zu laufen -
 * siehe AppSetting::logoUrl(), das zwischen beiden Faellen unterscheidet.
 *
 * SVG-Risiko: eine hochgeladene SVG-Datei koennte Skript enthalten. Die
 * erste Verteidigungslinie ist die Inhaltspruefung beim Upload (siehe
 * App\Rules\RejectsUnsafeSvg, eingebunden in
 * App\Filament\Pages\AppearanceSettings). Der Content-Security-Policy-Header
 * hier ist eine zweite, unabhaengige Linie: er unterbindet
 * Skriptausfuehrung auch dann, wenn eine hochgeladene Datei die Pruefung
 * dennoch durchrutschen sollte - insbesondere bei einer direkten Navigation
 * zu dieser URL. Wird das Logo nur wie vorgesehen per <img> oder als
 * Favicon eingebunden, fuehrt der Browser eingebettetes Skript ohnehin nicht
 * aus; der Header schuetzt den Fall, dass jemand die URL direkt oeffnet.
 *
 * response()->file() (statt Storage::response()) liefert eine
 * BinaryFileResponse, die Last-Modified automatisch setzt und bedingte
 * Anfragen (If-Modified-Since -> 304) selbst behandelt - zusammen mit dem
 * Cache-Control-Header unten muss der Browser das Logo dadurch nicht bei
 * jedem Seitenaufruf neu laden.
 */
class BrandLogoController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $path = AppSetting::logoStoragePath();

        abort_if(blank($path) || ! Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
