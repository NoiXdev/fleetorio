<?php

use App\Http\Controllers\SetupController;
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
