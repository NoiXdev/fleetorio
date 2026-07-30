<?php

namespace App\Http\Controllers;

use App\Models\VehicleDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Einziger Weg, eine Dokumentdatei auszuliefern. Absichtlich kein
 * Storage::url() und keine temporaere signierte URL: der Disk 'local' liegt
 * unter storage/app/private, ist vom Webserver aus nicht erreichbar, und
 * jede Anfrage hier prueft bei jedem Aufruf erneut die Berechtigung -
 * gegenueber einer signierten URL, die einmal ausgestellt und dann ohne
 * weitere Pruefung so lange gueltig waere, wie ihre Signatur reicht.
 *
 * Zwei Ausgabewege ueber denselben Controller (Gate::authorize() also fuer
 * beide identisch, siehe view() unten): download() liefert die Datei mit
 * "Content-Disposition: attachment" (Speichern-Dialog), view() liefert
 * dieselbe Datei inline fuer die "Ansehen"-Aktion im
 * VehicleDocumentsRelationManager.
 *
 * Die Routen liegen hinter der Panel-Authentifizierung (routes/web.php,
 * Middleware 'auth' auf demselben 'web'-Guard, den das Filament-Panel
 * verwendet). Ein nicht angemeldeter Aufruf wird von dieser Middleware
 * bereits abgewiesen, bevor eine dieser Methoden ueberhaupt laeuft.
 */
class VehicleDocumentDownloadController extends Controller
{
    public function download(VehicleDocument $vehicleDocument): StreamedResponse
    {
        Gate::authorize('view', $vehicleDocument);

        return Storage::disk('local')->download(
            $vehicleDocument->file_path,
            $vehicleDocument->original_filename,
        );
    }

    /**
     * Fuer die "Ansehen"-Aktion im RelationManager: dieselbe Autorisierung
     * und derselbe Disk-Zugriff wie download(), aber Storage::response()
     * setzt "Content-Disposition: inline" statt "attachment" (siehe
     * FilesystemAdapter::response() vs. download()) - ein <img> oder
     * <iframe> zeigt die Datei dadurch direkt an, statt einen
     * Speichern-Dialog auszuloesen.
     */
    public function view(VehicleDocument $vehicleDocument): StreamedResponse
    {
        Gate::authorize('view', $vehicleDocument);

        return Storage::disk('local')->response(
            $vehicleDocument->file_path,
            $vehicleDocument->original_filename,
        );
    }
}
