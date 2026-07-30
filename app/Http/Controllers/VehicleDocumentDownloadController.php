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
 * Die Route liegt hinter der Panel-Authentifizierung (routes/web.php,
 * Middleware 'auth' auf demselben 'web'-Guard, den das Filament-Panel
 * verwendet). Ein nicht angemeldeter Aufruf wird von dieser Middleware
 * bereits abgewiesen, bevor diese Methode ueberhaupt laeuft.
 */
class VehicleDocumentDownloadController extends Controller
{
    public function __invoke(VehicleDocument $vehicleDocument): StreamedResponse
    {
        Gate::authorize('view', $vehicleDocument);

        return Storage::disk('local')->download(
            $vehicleDocument->file_path,
            $vehicleDocument->original_filename,
        );
    }
}
