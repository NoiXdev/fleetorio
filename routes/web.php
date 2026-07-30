<?php

use App\Http\Controllers\SetupController;
use App\Http\Controllers\VehicleDocumentDownloadController;
use App\Http\Controllers\VehicleImageController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 * Die öffentliche Startseite ist bewusst datenfrei: sie ist ohne Anmeldung
 * erreichbar, und selbst aggregierte Zahlen wie die Fuhrparkgröße würden dort
 * Firmeninterna preisgeben. Sie führt nur zur Anmeldung oder - solange noch
 * kein Benutzer existiert - zur Ersteinrichtung; alles Weitere liegt hinter
 * /admin. Eine Closure statt einer reinen View-Route genügt bereits, um genau
 * diese eine Information (existiert bereits ein Benutzer?) zu übergeben.
 */
Route::get('/', fn () => view('public.overview', [
    'hasUsers' => User::query()->exists(),
]))->name('home');

/*
 * Ersteinrichtung, siehe SetupController für die Absicherung im Detail: die
 * Route bleibt nur erreichbar, solange kein Benutzer existiert - das prüft
 * der Controller bei jedem Aufruf serverseitig, für GET und POST getrennt.
 * Rate-Limiting auf dem Absenden, damit die Route nicht als Spam-Ziel taugt.
 */
Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('setup.store');

/*
 * Laravels Standard-'auth'-Middleware faellt bei einem nicht angemeldeten
 * Aufruf auf route('login') zurueck (Illuminate\Foundation\Exceptions\Handler
 * ::unauthenticated()) - ohne diese benannte Route wuerde das mit einer
 * RouteNotFoundException statt einer sauberen Weiterleitung enden. Das Panel
 * selbst kennt nur 'filament.admin.auth.login'; diese Route bildet den
 * generischen Namen 'login' schlicht darauf ab.
 */
Route::redirect('/login', '/admin/login')->name('login');

/*
 * Einziger Weg, eine Dokumentdatei auszuliefern - siehe Kommentar am
 * Controller. 'auth' (Guard 'web', siehe config/auth.php) ist derselbe
 * Session-Guard, den auch das Filament-Panel verwendet: ein Aufruf ohne
 * Anmeldung wird hier zur Login-Seite umgeleitet (302), bevor der Controller
 * ueberhaupt laeuft. Zwei Routen auf denselben Controller: /download erzwingt
 * den Speichern-Dialog, /view liefert dieselbe Datei inline fuer die
 * "Ansehen"-Aktion im VehicleDocumentsRelationManager.
 */
Route::get('/vehicle-documents/{vehicleDocument}/download', [VehicleDocumentDownloadController::class, 'download'])
    ->middleware('auth')
    ->name('vehicle-documents.download');

Route::get('/vehicle-documents/{vehicleDocument}/view', [VehicleDocumentDownloadController::class, 'view'])
    ->middleware('auth')
    ->name('vehicle-documents.view');

/*
 * Einziger Weg, ein Fahrzeugbild auszuliefern - siehe Kommentar am
 * Controller. Dieselbe Absicherung wie bei den Fahrzeugdokumenten oben:
 * 'auth'-Middleware weist einen nicht angemeldeten Aufruf ab, bevor der
 * Controller ueberhaupt laeuft.
 */
Route::get('/vehicle-images/{vehicle}/show', VehicleImageController::class)
    ->middleware('auth')
    ->name('vehicle-images.show');
