<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Einziger Weg, ein Fahrzeugbild auszuliefern - dasselbe Muster wie
 * VehicleDocumentDownloadController (siehe Kommentar dort). Ein Fahrzeugfoto
 * zeigt in aller Regel das Kennzeichen und darf deshalb nicht ohne Anmeldung
 * abrufbar sein: der Disk 'local' liegt unter storage/app/private, ist vom
 * Webserver aus nicht erreichbar, und jede Anfrage hier prueft bei jedem
 * Aufruf erneut die Berechtigung - bewusst kein Storage::url() und keine
 * temporaere signierte URL.
 *
 * response() statt download(): das Bild soll inline in <img>-Tags erscheinen
 * (Fahrzeugliste, Anzeigeseite, Formularvorschau), nicht als Dateidownload
 * mit Speichern-Dialog.
 *
 * Die Route liegt hinter der Panel-Authentifizierung (routes/web.php,
 * Middleware 'auth' auf demselben 'web'-Guard, den das Filament-Panel
 * verwendet). Ein nicht angemeldeter Aufruf wird von dieser Middleware
 * bereits abgewiesen, bevor diese Methode ueberhaupt laeuft.
 */
class VehicleImageController extends Controller
{
    public function __invoke(Vehicle $vehicle): StreamedResponse
    {
        Gate::authorize('view', $vehicle);

        abort_if(blank($vehicle->image_path), 404);

        return Storage::disk('local')->response($vehicle->image_path);
    }
}
