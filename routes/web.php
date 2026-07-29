<?php

use Illuminate\Support\Facades\Route;

/*
 * Die öffentliche Startseite ist bewusst datenfrei: sie ist ohne Anmeldung
 * erreichbar, und selbst aggregierte Zahlen wie die Fuhrparkgröße würden dort
 * Firmeninterna preisgeben. Sie führt nur zur Anmeldung; alles Weitere liegt
 * hinter /admin. Deshalb genügt eine View-Route ohne Controller.
 */
Route::view('/', 'public.overview')->name('home');
